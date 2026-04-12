(() => {
    document.querySelectorAll('.js-channel-delete').forEach((button) => {
        button.addEventListener('click', () => {
            const formId = button.dataset.formId;
            const form = formId ? document.getElementById(formId) : null;

            if (!form) {
                return;
            }

            if (typeof window.showConfirmDialog === 'function') {
                window.showConfirmDialog({
                    title: '确认删除这个栏目？',
                    text: '删除后如果该栏目仍有子栏目或内容占用，系统会阻止删除。请确认已经清理相关依赖后再继续。',
                    confirmText: '确认删除',
                    onConfirm: () => form.submit(),
                });
                return;
            }

            if (window.confirm('确认删除这个栏目？')) {
                form.submit();
            }
        });
    });

    const rows = Array.from(document.querySelectorAll('[data-channel-row]'));
    const rowMap = new Map(rows.map((row) => [row.dataset.channelId, row]));
    const expanded = new Set(
        rows
            .filter((row) => Number(row.dataset.depth || 0) === 0)
            .map((row) => row.dataset.channelId)
            .filter(Boolean)
    );

    const hasExpandedAncestor = (row) => {
        let parentId = row.dataset.parentId;

        while (parentId && rowMap.has(parentId)) {
            if (!expanded.has(parentId)) {
                return false;
            }
            parentId = rowMap.get(parentId).dataset.parentId;
        }

        return true;
    };

    const syncTree = () => {
        rows.forEach((row) => {
            const depth = Number(row.dataset.depth || 0);
            row.hidden = !(depth === 0 || hasExpandedAncestor(row));
        });

        document.querySelectorAll('[data-channel-toggle]').forEach((toggle) => {
            const isOpen = expanded.has(toggle.dataset.channelToggle);
            toggle.classList.toggle('is-expanded', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    };

    document.querySelectorAll('.channel-tree-content[data-toggle-children]').forEach((toggle) => {
        const handler = () => {
            const id = toggle.dataset.channelToggle;
            if (!id) {
                return;
            }

            if (expanded.has(id)) {
                expanded.delete(id);
            } else {
                expanded.add(id);
            }

            syncTree();
        };

        toggle.addEventListener('click', handler);
        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                handler();
            }
        });
    });

    syncTree();

    const tbody = document.querySelector('tbody[data-channel-reorder-url]');
    const reorderUrl = tbody?.dataset.channelReorderUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let dragState = null;

    const getRowDepth = (row) => Number(row?.dataset.depth || 0);
    const getRowParentId = (row) => row?.dataset.parentId || '';
    const getSubtreeRows = (row) => {
        const depth = getRowDepth(row);
        const subtreeRows = [row];
        let nextRow = row.nextElementSibling;

        while (nextRow && getRowDepth(nextRow) > depth) {
            subtreeRows.push(nextRow);
            nextRow = nextRow.nextElementSibling;
        }

        return subtreeRows;
    };

    const appendSubtreeAfterRow = (row, subtreeRows) => {
        let anchor = row.nextElementSibling;
        subtreeRows.forEach((childRow) => {
            tbody?.insertBefore(childRow, anchor);
        });
    };

    const siblingIdsForParent = (parentId) => Array.from(tbody?.querySelectorAll('tr[data-channel-row]') || [])
        .filter((row) => getRowParentId(row) === parentId)
        .map((row) => Number(row.dataset.channelId));

    const saveReorder = async (parentId, orderedIds) => {
        const response = await fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                parent_id: parentId === '' ? null : Number(parentId),
                ordered_ids: orderedIds,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || '栏目排序保存失败，请稍后重试。');
        }

        return payload;
    };

    if (tbody && reorderUrl && window.Sortable) {
        Sortable.create(tbody, {
            animation: 180,
            handle: '.channel-drag-handle',
            draggable: 'tr[data-channel-row]',
            ghostClass: 'channel-row-ghost',
            chosenClass: 'channel-row-chosen',
            dragClass: 'channel-row-drag',
            onStart(event) {
                const row = event.item;
                const subtreeRows = getSubtreeRows(row);
                const descendants = subtreeRows.slice(1);

                dragState = {
                    rowId: row.dataset.channelId || '',
                    parentId: getRowParentId(row),
                    descendants,
                    originalSiblingIds: siblingIdsForParent(getRowParentId(row)),
                };

                descendants.forEach((childRow) => childRow.remove());
                row.classList.add('is-sorting');
            },
            onMove(event) {
                if (!dragState) {
                    return true;
                }

                const related = event.related;

                if (!related) {
                    return false;
                }

                return getRowParentId(related) === dragState.parentId;
            },
            async onEnd(event) {
                const row = event.item;
                const currentState = dragState;
                row.classList.remove('is-sorting');

                if (!currentState) {
                    syncTree();
                    return;
                }

                appendSubtreeAfterRow(row, currentState.descendants);
                syncTree();

                const nextSiblingIds = siblingIdsForParent(currentState.parentId);
                dragState = null;

                if (JSON.stringify(nextSiblingIds) === JSON.stringify(currentState.originalSiblingIds)) {
                    return;
                }

                row.classList.add('is-saving');

                try {
                    const payload = await saveReorder(currentState.parentId, nextSiblingIds);
                    if (typeof window.showMessage === 'function') {
                        window.showMessage(payload.message || '栏目排序已保存。');
                    }
                } catch (error) {
                    if (typeof window.showMessage === 'function') {
                        window.showMessage(error.message || '栏目排序保存失败，页面将刷新恢复。', 'error');
                    }

                    window.setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } finally {
                    row.classList.remove('is-saving');
                }
            },
        });
    }
})();
