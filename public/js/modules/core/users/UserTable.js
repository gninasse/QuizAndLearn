/**
 * UserTable.js
 * Handles Bootstrap Table configuration and selection events.
 */
export class UserTable {
    constructor(tableSelector) {
        this.$table = $(tableSelector);
        this.$btnEdit = $('#btn-edit-user');
        this.$btnDelete = $('#btn-delete-user');
        this.$btnReset = $('#btn-reset-password');
        this.$btnEnable = $('#btn-enable-user');
        this.$btnDisable = $('#btn-disable-user');
    }

    init() {
        this.initFormatters();
        this.initEvents();
        console.log("UserTable initialized");
    }

    initFormatters() {
        // Expose formatters to window for bootstrap-table data-formatter attribute
        window.statusFormatter = (value, row, index) => {
            if (value) {
                return '<span class="badge bg-success">Actif</span>';
            } else {
                return '<span class="badge bg-danger">Inactif</span>';
            }
        };

        window.dateFormatter = (value, row, index) => {
            if (value) {
                return new Date(value).toLocaleDateString('fr-FR', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            return '-';
        };
    }

    initEvents() {
        this.$table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table load-success.bs.table', () => {
            this.handleSelection();
        });
    }

    handleSelection() {
        const selections = this.$table.bootstrapTable('getSelections');
        const hasSelection = selections.length > 0;
        const isSingle = selections.length === 1;

        this.$btnEdit.prop('disabled', !isSingle);
        this.$btnDelete.prop('disabled', !isSingle);
        this.$btnReset.prop('disabled', !isSingle);

        if (isSingle) {
            const row = selections[0];
            if (row.is_active == 1) {
                this.$btnEnable.hide();
                this.$btnDisable.show().prop('disabled', false);
            } else {
                this.$btnEnable.show().prop('disabled', false);
                this.$btnDisable.hide();
            }
        } else {
            this.$btnEnable.hide();
            this.$btnDisable.hide();
        }
    }

    getSelectedId() {
        const selections = this.$table.bootstrapTable('getSelections');
        if (selections.length === 1) {
            return selections[0].id;
        }
        return null;
    }

    refresh() {
        this.$table.bootstrapTable('refresh');
    }
}
