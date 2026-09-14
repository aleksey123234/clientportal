/**
 * CIF module: tables
 * Dynamic rows.
 */
(function (Cif) {
    'use strict';

Cif.buildTableRowFromCols = function (cols, rowIdx) {
        var tr = document.createElement('tr');
        tr.dataset.row = String(rowIdx);
        var numTd = document.createElement('td');
        numTd.className = 'text-center text-muted align-middle cif-row-num';
        numTd.textContent = String(rowIdx + 1);
        tr.appendChild(numTd);
        cols.forEach(function (c) {
            var td = document.createElement('td');
            if (c.type === 'checkbox') {
                td.innerHTML =
                    '<div class="form-check d-flex justify-content-center m-0">' +
                    '<input class="form-check-input cif-table-input" type="checkbox" data-col="' +
                    c.key +
                    '"></div>';
            } else {
                var colType = c.type || 'text';
                var cls = 'form-control form-control-sm cif-table-input';
                var extra = '';
                if (colType === 'month') {
                    cls += ' cif-month-picker';
                    extra = ' placeholder="yyyy-mm" readonly';
                } else if (colType === 'date') {
                    cls += ' cif-date-picker';
                    extra = ' placeholder="Select date…" readonly';
                }
                td.innerHTML =
                    '<input type="text" class="' +
                    cls +
                    '" data-col="' +
                    c.key +
                    '" data-col-type="' +
                    colType +
                    '"' +
                    extra +
                    ' value="">';
            }
            tr.appendChild(td);
        });
        var act = document.createElement('td');
        act.className = 'text-center align-middle';
        act.innerHTML =
            '<button type="button" class="btn btn-sm btn-outline-danger cif-row-remove" title="Remove row">' +
            '<i class="bi bi-x-lg"></i></button>';
        tr.appendChild(act);
        return tr;
    }

    // Add row
    document.querySelectorAll('.cif-row-add').forEach((btn) => {
        btn.addEventListener('click', function () {
            const tableKey = this.dataset.table;
            const tableEl = document.querySelector(
                `.cif-table[data-key="${tableKey}"]`
            );
            if (!tableEl) return;
            const table = tableEl.querySelector('tbody');
            if (!table) return;

            const lastRow = table.querySelector('tr:last-child');
            const rowIdx = table.querySelectorAll('tr').length;
            var newRow;
            if (lastRow) {
                newRow = lastRow.cloneNode(true);
                newRow.dataset.row = rowIdx;
                const numCell = newRow.querySelector('.cif-row-num');
                if (numCell) numCell.textContent = rowIdx + 1;
                newRow
                    .querySelectorAll('input[type="text"]')
                    .forEach(function (inp) {
                        if (inp._flatpickr) inp._flatpickr.destroy();
                        var next = inp.nextElementSibling;
                        while (
                            next &&
                            next.classList &&
                            next.classList.contains('flatpickr-calendar')
                        ) {
                            next.remove();
                            next = inp.nextElementSibling;
                        }
                        inp.value = '';
                        if (inp.dataset.col === 'to' && tableEl.dataset.presentRow) {
                            inp.readOnly = false;
                        }
                    });
                newRow
                    .querySelectorAll('input[type="checkbox"]')
                    .forEach((cb) => (cb.checked = false));
                const removeBtn = newRow.querySelector('.cif-row-remove');
                if (removeBtn) removeBtn.disabled = false;
            } else {
                var cols = [];
                try {
                    cols = JSON.parse(tableEl.dataset.cols || '[]');
                } catch (e) {
                    cols = [];
                }
                newRow = Cif.buildTableRowFromCols(cols, rowIdx);
            }

            table.appendChild(newRow);
            Cif.bindRowRemove(newRow);
            Cif.bindInputListeners(newRow);
            Cif.initPickersInContainer(newRow);
            Cif.updateProgress();
        });
    });

    // Remove row (delegated)
    Cif.bindRowRemove = function (row) {
        const btn = row.querySelector('.cif-row-remove');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const tbody = row.closest('tbody');
            const tableEl = row.closest('.cif-table');
            const allowEmpty = tableEl && tableEl.dataset.allowEmpty === '1';
            if (!allowEmpty && tbody.querySelectorAll('tr').length <= 1) return;
            row.remove();
            tbody.querySelectorAll('tr').forEach((tr, i) => {
                tr.dataset.row = i;
                const num = tr.querySelector('.cif-row-num');
                if (num) num.textContent = i + 1;
                const rb = tr.querySelector('.cif-row-remove');
                if (rb && !allowEmpty) rb.disabled = i === 0;
            });
            Cif.updateProgress();
        });
    }

    // Bind existing remove buttons
    document.querySelectorAll('.cif-row-remove').forEach((btn) => {
        const row = btn.closest('tr');
        if (row) Cif.bindRowRemove(row);
    });

})(window.Cif = window.Cif || {});
