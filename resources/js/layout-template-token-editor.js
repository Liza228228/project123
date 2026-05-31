// скрипт на странице
let draggingLayoutToken = null;

function caretRangeFromPoint(x, y) {
    if (document.caretRangeFromPoint) {
        return document.caretRangeFromPoint(x, y);
    }
    const pos = document.caretPositionFromPoint?.(x, y);
    if (!pos || !pos.offsetNode) {
        return null;
    }
    const r = document.createRange();
    try {
        r.setStart(pos.offsetNode, pos.offset);
        r.collapse(true);
        return r;
    } catch {
        return null;
    }
}

export function layoutTokenEditorMixin() {
    const refByTarget = {
        heading: 'headingEditor',
        header: 'headerEditor',
        body: 'bodyEditor',
        footer: 'footerEditor',
        signature: 'signatureEditor',
    };

    const propByTarget = {
        heading: 'headingTemplate',
        header: 'headerTemplate',
        body: 'bodyTemplate',
        footer: 'footerTemplate',
        signature: 'signatureTemplate',
    };

    return {
        labelForTokenKey(key) {
            const k = String(key || '');
            const f = (this.fields || []).find((x) => x.key === k);
            if (f && f.label && String(f.label).trim()) {
                return String(f.label).trim();
            }
            const sys = {
                coordinator_name: 'ФИО согласующего',
                representative_prefix: 'Префикс исполнителя',
                representative_name: 'Исполнитель',
                signatory_print_name: 'Подпись (печать)',
                subdivision_name: 'Подразделение',
                document_date: 'Дата документа',
                report_date: 'Дата',
                document_number: 'Номер документа',
                report_number: 'Номер',
                contract_date: 'Дата контракта',
                contract_number: 'Номер контракта',
                approver_fio: 'Согласующий',
                executor_line1: 'Строка исполнителя 1',
                executor_line2: 'Строка исполнителя 2',
                signatory_fio: 'Подпись',
                department_name: 'Подразделение',
            };
            return sys[k] || k;
        },

        makeTokenSpan(key) {
            const span = document.createElement('span');
            span.setAttribute('data-layout-key', String(key));
            span.contentEditable = 'false';
            span.draggable = true;
            span.className =
                'layout-token-chip inline-block align-baseline max-w-[min(100%,14rem)] truncate cursor-grab rounded-md border border-orange-300/90 bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-950 shadow-sm selection:bg-transparent dark:border-orange-700 dark:bg-orange-950/70 dark:text-orange-100 active:cursor-grabbing';
            span.title = '{{' + key + '}} — перетащите, чтобы поменять место в тексте';
            span.textContent = this.labelForTokenKey(key);
            span.addEventListener('dragstart', (e) => {
                draggingLayoutToken = span;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', '');
                span.classList.add('opacity-60', 'ring-2', 'ring-orange-400');
            });
            span.addEventListener('dragend', () => {
                span.classList.remove('opacity-60', 'ring-2', 'ring-orange-400');
                draggingLayoutToken = null;
            });
            return span;
        },

        renderTokenEditor(el, template) {
            if (!el) {
                return;
            }
            const t = template == null ? '' : String(template);
            el.innerHTML = '';
            const re = /\{\{\s*([^}]+?)\s*\}\}/gu;
            let last = 0;
            let m;
            while ((m = re.exec(t)) !== null) {
                if (m.index > last) {
                    el.appendChild(document.createTextNode(t.slice(last, m.index)));
                }
                el.appendChild(this.makeTokenSpan(String(m[1]).trim()));
                last = re.lastIndex;
            }
            if (last < t.length) {
                el.appendChild(document.createTextNode(t.slice(last)));
            }
        },

        serializeTokenEditor(el) {
            if (!el) {
                return '';
            }
            let out = '';
            const walk = (node) => {
                if (node.nodeType === Node.TEXT_NODE) {
                    out += node.textContent;
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    const tag = node.tagName;
                    if (tag === 'BR') {
                        out += '\n';
                    } else if (node.matches && node.matches('span[data-layout-key]')) {
                        out += '{{' + node.getAttribute('data-layout-key') + '}}';
                    } else {
                        node.childNodes.forEach(walk);
                    }
                }
            };
            el.childNodes.forEach(walk);
            return out;
        },

        syncTargetTemplate(target) {
            const refName = refByTarget[target];
            const prop = propByTarget[target];
            if (!refName || !prop) {
                return;
            }
            const el = this.$refs[refName];
            if (!el) {
                return;
            }
            this[prop] = this.serializeTokenEditor(el);
        },

        syncAllTokenEditors() {
            ['heading', 'header', 'body', 'footer', 'signature'].forEach((t) => {
                const refName =
                    t === 'heading'
                        ? 'headingEditor'
                        : t === 'header'
                          ? 'headerEditor'
                          : t === 'body'
                            ? 'bodyEditor'
                            : t === 'footer'
                              ? 'footerEditor'
                              : 'signatureEditor';
                if (this.$refs[refName]) {
                    this.syncTargetTemplate(t);
                }
            });
        },

        initAllTokenEditors() {
            this.$nextTick(() => {
                const pairs = [
                    ['headingEditor', 'headingTemplate'],
                    ['headerEditor', 'headerTemplate'],
                    ['bodyEditor', 'bodyTemplate'],
                    ['footerEditor', 'footerTemplate'],
                    ['signatureEditor', 'signatureTemplate'],
                ];
                pairs.forEach(([ref, prop]) => {
                    const el = this.$refs[ref];
                    if (el && this[prop] !== undefined) {
                        this.renderTokenEditor(el, this[prop]);
                    }
                });
            });
        },

        refreshTokenChipLabels() {
            [
                'headingEditor',
                'headerEditor',
                'bodyEditor',
                'footerEditor',
                'signatureEditor',
            ].forEach((refName) => {
                const root = this.$refs[refName];
                if (!root) {
                    return;
                }
                root.querySelectorAll('span[data-layout-key]').forEach((span) => {
                    const k = span.getAttribute('data-layout-key');
                    span.textContent = this.labelForTokenKey(k);
                    span.title = '{{' + k + '}}';
                });
            });
        },

        insertToken(key, target) {
            const refName = refByTarget[target];
            if (!key || !refName) {
                return;
            }
            const el = this.$refs[refName];
            if (!el) {
                return;
            }
            const span = this.makeTokenSpan(key);
            el.focus();

            const sel = window.getSelection();
            if (!sel.rangeCount || !el.contains(sel.anchorNode)) {
                el.appendChild(span);
                const r = document.createRange();
                r.setStartAfter(span);
                r.collapse(true);
                sel.removeAllRanges();
                sel.addRange(r);
            } else {
                const range = sel.getRangeAt(0);
                range.deleteContents();
                range.insertNode(span);
                range.setStartAfter(span);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
            }

            this.syncTargetTemplate(target);
        },

        onTokenEditorPaste(e) {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text/plain');
            if (!text) {
                return;
            }
            document.execCommand('insertText', false, text);
        },

        onTokenEditorKeydown(e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            document.execCommand('insertLineBreak');
        },

        onTokenEditorDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        },
        onTokenEditorDrop(e, target) {
            e.preventDefault();
            const dragged = draggingLayoutToken;
            const refName = refByTarget[target];
            if (!dragged || !dragged.getAttribute('data-layout-key') || !refName) {
                return;
            }
            const editor = this.$refs[refName];
            if (!editor || !editor.contains(dragged)) {
                return;
            }

            dragged.remove();

            const x = e.clientX;
            const y = e.clientY;
            let elBelow = document.elementFromPoint(x, y);
            if (!elBelow) {
                editor.appendChild(dragged);
                this.syncTargetTemplate(target);
                return;
            }
            if (!editor.contains(elBelow)) {
                elBelow = editor;
            }

            const chip =
                elBelow.closest && elBelow !== editor
                    ? elBelow.closest('span[data-layout-key]')
                    : null;

            if (chip && editor.contains(chip) && chip !== dragged) {
                const rect = chip.getBoundingClientRect();
                const before = x < rect.left + rect.width / 2;
                if (before) {
                    chip.parentNode.insertBefore(dragged, chip);
                } else {
                    chip.parentNode.insertBefore(dragged, chip.nextSibling);
                }
            } else {
                const range = caretRangeFromPoint(x, y);
                if (
                    range &&
                    editor.contains(range.startContainer) &&
                    range.startContainer !== dragged
                ) {
                    try {
                        range.insertNode(dragged);
                    } catch {
                        editor.appendChild(dragged);
                    }
                } else {
                    editor.appendChild(dragged);
                }
            }

            this.syncTargetTemplate(target);
        },
    };
}
