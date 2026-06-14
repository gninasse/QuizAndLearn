/**
 * AdminTable.js
 * Handles Bootstrap Table configuration and selection events for administrators.
 */
export class AdminTable {
    constructor(tableSelector) {
        this.$table = $(tableSelector);
        this.$btnEdit = $('#btn-edit-admin');
        this.$btnDelete = $('#btn-delete-admin');
        this.$btnReset = $('#btn-reset-password');
        this.$btnEnable = $('#btn-enable-admin');
        this.$btnDisable = $('#btn-disable-admin');
    }

    init() {
        this.initEvents();
        console.log("AdminTable initialized");
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
