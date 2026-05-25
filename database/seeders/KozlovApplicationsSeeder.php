<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationInstallationActPhoto;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\Equipment;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\User;
use App\Support\AdministrationWarehouse;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class KozlovApplicationsSeeder extends Seeder
{
    /** @var string Корень зеркала storage в public/ (в git). */
    private const INSTALLATION_MEDIA_PUBLIC_DIR = 'seeders/kozlov-installation';

    private const INSTALLATION_ACTS_DIR = 'installation-acts';

    private const INSTALLATION_ACT_PHOTOS_DIR = 'installation-act-photos';

    private ?array $installationMedia = null;

    public function run(): void
    {
        $kozlov = User::query()->where('email', 'Kozlov@mail.ru')->first();
        if (! $kozlov) {
            return;
        }

        $draftId = ApplicationStatus::idForDraft();
        $pendingId = ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
        $approvedId = ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED);
        $partialId = ApplicationStatus::idFor(ApplicationStatus::NAME_PARTIAL);
        $rejectedId = ApplicationStatus::idFor(ApplicationStatus::NAME_REJECTED);
        $completedId = ApplicationStatus::idFor(ApplicationStatus::NAME_COMPLETED);
        $transportQuery = TransportOption::query()->orderBy('id');
        if (Schema::hasColumn('transport_options', 'plate')) {
            $transportQuery->whereNull('plate');
        }
        $transportId = (int) ($transportQuery->value('id') ?? 0);
        $adminSubdivisionId = AdministrationWarehouse::subdivisionId();
        $allSubdivisionIds = Subdivision::query()
            ->when($adminSubdivisionId !== null, fn ($q) => $q->where('id', '!=', $adminSubdivisionId))
            ->orderBy('id')
            ->pluck('id')
            ->all();
        if ($allSubdivisionIds === []) {
            return;
        }

        $assignedSubdivisionIds = $kozlov->assignedSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->all();
        if ($adminSubdivisionId !== null) {
            $assignedSubdivisionIds = array_values(array_filter(
                $assignedSubdivisionIds,
                fn (int $id): bool => $id !== $adminSubdivisionId,
            ));
            if ($assignedSubdivisionIds !== []) {
                $kozlov->assignedSubdivisions()->sync($assignedSubdivisionIds);
            }
        }
        if ($assignedSubdivisionIds === []) {
            $assignedSubdivisionIds = array_slice($allSubdivisionIds, 0, min(3, count($allSubdivisionIds)));
            $kozlov->assignedSubdivisions()->sync($assignedSubdivisionIds);
        }

        $equipmentIds = Equipment::query()
            ->where('is_catalog', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (
            $draftId <= 0
            || $pendingId <= 0
            || $approvedId <= 0
            || $partialId <= 0
            || $rejectedId <= 0
            || $completedId <= 0
            || $transportId <= 0
            || count($equipmentIds) < 5
        ) {
            return;
        }

        Application::query()
            ->where('responsible_user_id', (int) $kozlov->id)
            ->where('user_id', (int) $kozlov->id)
            ->delete();

        $targetCount = 30;
        $statusCycle = [
            ['id' => $draftId, 'type' => 'draft'],
            ['id' => $pendingId, 'type' => 'pending'],
            ['id' => $approvedId, 'type' => 'approved'],
            ['id' => $partialId, 'type' => 'partial'],
            ['id' => $rejectedId, 'type' => 'rejected'],
            ['id' => $completedId, 'type' => 'completed'],
        ];
        $directorId = (int) (User::query()->where('role_id', 1)->value('id') ?? 0);

        for ($i = 0; $i < $targetCount; $i++) {
            $statusMeta = $statusCycle[$i % count($statusCycle)];
            $statusId = (int) $statusMeta['id'];
            $statusType = (string) $statusMeta['type'];

            $reason = match ($statusType) {
                'rejected' => 'Тестовый отказ (сидер Козлова).',
                default => null,
            };

            $app = Application::query()->create([
                'subdivision_id' => (int) $assignedSubdivisionIds[$i % count($assignedSubdivisionIds)],
                'responsible_user_id' => (int) $kozlov->id,
                'user_id' => (int) $kozlov->id,
                'transport_option_id' => $transportId,
                'application_status_id' => $statusId,
                'approved_by_user_id' => $directorId > 0 && ! in_array($statusType, ['draft', 'pending'], true) ? $directorId : null,
                'reason_for_refusal' => $reason,
                'desired_delivery_date' => Carbon::now()->addDays(2 + $i),
            ]);

            $itemsCount = random_int(5, 10);
            $shuffledEquipment = $equipmentIds;
            shuffle($shuffledEquipment);

            for ($line = 0; $line < $itemsCount; $line++) {
                $equipmentId = (int) $shuffledEquipment[$line % count($shuffledEquipment)];
                $isChecked = match ($statusType) {
                    'approved', 'completed' => true,
                    'rejected' => false,
                    'partial' => $line < max(1, (int) floor($itemsCount / 2)),
                    default => false,
                };
                $reasonNotSelected = ! $isChecked && ! in_array($statusType, ['draft', 'pending'], true)
                  ? 'Нет на складе.'
                  : null;
                $deliveryStatusId = null;
                if ($isChecked) {
                    $deliveryStatusId = $statusType === 'completed'
                      ? ApplicationItem::DELIVERY_DELIVERED_ID
                      : (($line % 2 === 0)
                        ? ApplicationItem::DELIVERY_DELIVERED_ID
                        : ApplicationItem::DELIVERY_IN_TRANSIT_ID);
                }

                ApplicationItem::query()->create([
                    'application_id' => (int) $app->id,
                    'equipment_id' => $equipmentId,
                    'quantity' => random_int(1, 25),
                    'is_checked' => $isChecked,
                    'reason_not_selected' => $reasonNotSelected,
                    'delivery_status_id' => $deliveryStatusId,
                ]);
            }
        }

        $this->attachInstallationActAndPhotoToAllCompletedApplications((int) $kozlov->id, $completedId);

        $this->call(KozlovCompletedApplicationsWarehouseSeeder::class);
    }

    private function attachInstallationActAndPhotoToAllCompletedApplications(int $kozlovUserId, int $completedStatusId): void
    {
        $completedApplications = Application::query()
            ->where('user_id', $kozlovUserId)
            ->where('responsible_user_id', $kozlovUserId)
            ->where('application_status_id', $completedStatusId)
            ->orderBy('id')
            ->get();

        foreach ($completedApplications as $index => $application) {
            $this->attachInstallationActAndPhoto($application, $index);
        }
    }

    private function attachInstallationActAndPhoto(Application $application, int $completedIndex): void
    {
        $media = $this->installationMediaConfig();
        $actFilename = (string) $media['act_filename'];
        $photoFilenames = $media['photo_filenames'];
        $photoFilename = (string) $photoFilenames[$completedIndex % count($photoFilenames)];

        $applicationId = (int) $application->id;
        $actPath = 'installation-acts/'.$applicationId.'/'.$actFilename;
        $photoPath = 'installation-act-photos/'.$applicationId.'/'.$photoFilename;

        $disk = Storage::disk('public');
        $this->publishInstallationMediaFile($disk, $actPath, $actFilename, self::INSTALLATION_ACTS_DIR, $completedIndex);
        $this->publishInstallationMediaFile($disk, $photoPath, $photoFilename, self::INSTALLATION_ACT_PHOTOS_DIR, $completedIndex);

        $application->update(['act_of_installation' => $actPath]);

        $application->installationActPhotos()->delete();
        ApplicationInstallationActPhoto::query()->create([
            'application_id' => $applicationId,
            'path' => $photoPath,
        ]);
    }

    /**
     * @return array{act_filename: string, photo_filenames: list<string>}
     */
    private function installationMediaConfig(): array
    {
        if ($this->installationMedia !== null) {
            return $this->installationMedia;
        }

        $configPath = database_path('seeders/data/kozlov_installation_media.php');
        $this->installationMedia = is_file($configPath)
          ? require $configPath
          : [
              'act_filename' => 'zajavka-1 (11).pdf',
              'photo_filenames' => ['frisquet.jpg'],
          ];

        return $this->installationMedia;
    }

    private function publishInstallationMediaFile(
        Filesystem $disk,
        string $targetRelativePath,
        string $filename,
        string $publicSubdir,
        int $completedIndex,
    ): void {
        $disk->makeDirectory(dirname($targetRelativePath));

        $sourceAbsolutePath = $this->resolveInstallationMediaSourcePath($publicSubdir, $filename, $completedIndex);
        if ($sourceAbsolutePath === null) {
            if ($this->command !== null) {
                $slot = $this->installationMediaSlot($completedIndex);
                $this->command->warn(
                    'Файл для сидера не найден: '.$filename
                    .' (ожидается: public/'.self::INSTALLATION_MEDIA_PUBLIC_DIR.'/'.$publicSubdir.'/'.$slot.'/).'
                );
            }

            return;
        }

        $disk->put($targetRelativePath, (string) file_get_contents($sourceAbsolutePath));
    }

    /**
     * Номер папки в public (1…N), соответствует порядку выполненных заявок в сидере.
     */
    private function installationMediaSlot(int $completedIndex): int
    {
        return $completedIndex + 1;
    }

    /**
     * Источник в public: та же структура, что в storage, но вместо id заявки — slot.
     */
    private function resolveInstallationMediaSourcePath(string $publicSubdir, string $filename, int $completedIndex): ?string
    {
        $slot = $this->installationMediaSlot($completedIndex);
        $path = public_path(
            self::INSTALLATION_MEDIA_PUBLIC_DIR
            .DIRECTORY_SEPARATOR.$publicSubdir
            .DIRECTORY_SEPARATOR.$slot
            .DIRECTORY_SEPARATOR.$filename
        );

        return is_file($path) ? $path : null;
    }
}
