/**
 * LearnerTable.js
 * Handles Bootstrap Table configuration and selection events for learners.
 */
export class LearnerTable {
    constructor(tableSelector) {
        this.$table = $(tableSelector);
        this.$btnEdit = $('#btn-edit-learner');
        this.$btnDelete = $('#btn-delete-learner');
        this.$btnReset = $('#btn-reset-password');
        this.$btnEnable = $('#btn-enable-learner');
        this.$btnDisable = $('#btn-disable-learner');
    }

    init() {
        this.initEvents();
        console.log("LearnerTable initialized");
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
