<?php

namespace App\Http\Controllers;

use App\Models\DatabaseOperationLog;
use App\Services\DatabaseRestoreService;
use Illuminate\Http\BinaryFileResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AdminDatabaseRestoreController extends Controller
{
    public function index(): View
    {
        $logs = DatabaseOperationLog::query()
            ->with('user:id,surname,name,patronymic')
            ->orderByDesc('executed_at')
            ->paginate(15);

        return view('admin.database.restore', compact('logs'));
    }

    public function restore(Request $request, DatabaseRestoreService $databaseRestoreService): RedirectResponse
    {
        $validated = $request->validate([
            'dump_file' => ['required', 'file', 'mimes:sql', 'max:51200'],
            'confirm_phrase' => ['required', 'string'],
        ], [
            'dump_file.required' => 'Выберите SQL-файл для восстановления.',
            'dump_file.mimes' => 'Поддерживается только файл .sql.',
            'dump_file.max' => 'Максимальный размер файла: 50 МБ.',
            'confirm_phrase.required' => 'Подтвердите восстановление контрольной фразой.',
        ]);

        if (trim((string) $validated['confirm_phrase']) !== 'ВОССТАНОВИТЬ') {
            return back()
                ->withErrors(['confirm_phrase' => 'Введите точную фразу: ВОССТАНОВИТЬ'])
                ->withInput();
        }

        $disk = Storage::disk('local');
        $path = $request->file('dump_file')->store('db-restore-temp', 'local');
        $absolutePath = $disk->path($path);

        try {
            $databaseRestoreService->restoreFromSqlFile($absolutePath);
        } catch (Throwable $e) {
            DatabaseOperationLog::query()->create([
                'user_id' => $request->user()?->id,
                'operation_type' => 'restore',
                'status' => 'failed',
                'file_name' => (string) $request->file('dump_file')->getClientOriginalName(),
                'storage_path' => null,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'executed_at' => now(),
            ]);

            return back()
                ->withErrors(['dump_file' => 'Не удалось выполнить восстановление базы данных. Проверьте формат SQL-дампа и настройки окружения.'])
                ->withInput();
        } finally {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        DatabaseOperationLog::query()->create([
            'user_id' => $request->user()?->id,
            'operation_type' => 'restore',
            'status' => 'success',
            'file_name' => (string) $request->file('dump_file')->getClientOriginalName(),
            'storage_path' => null,
            'error_message' => null,
            'executed_at' => now(),
        ]);

        return redirect()
            ->route('admin.database.restore.index')
            ->with('status', 'Восстановление базы данных успешно завершено.');
    }

    public function backup(Request $request, DatabaseRestoreService $databaseRestoreService): BinaryFileResponse|RedirectResponse
    {
        try {
            $backup = $databaseRestoreService->createSqlBackup();
        } catch (Throwable $e) {
            DatabaseOperationLog::query()->create([
                'user_id' => $request->user()?->id,
                'operation_type' => 'backup',
                'status' => 'failed',
                'file_name' => 'backup_failed.sql',
                'storage_path' => null,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'executed_at' => now(),
            ]);

            return back()->withErrors([
                'backup' => 'Не удалось создать backup базы данных. Проверьте доступность CLI-утилит СУБД и параметры подключения.',
            ]);
        }

        DatabaseOperationLog::query()->create([
            'user_id' => $request->user()?->id,
            'operation_type' => 'backup',
            'status' => 'success',
            'file_name' => $backup['file_name'],
            'storage_path' => $backup['storage_path'],
            'error_message' => null,
            'executed_at' => now(),
        ]);

        return Storage::disk('local')
            ->download($backup['storage_path'], $backup['file_name'])
            ->deleteFileAfterSend(true);
    }
}
