/**
 * QuizTable.js
 * Handles Bootstrap Table configuration and selection events for quizzes.
 */
export class QuizTable {
    constructor(tableSelector) {
        this.$table = $(tableSelector);
        this.$btnEdit = $('#btn-edit-quiz');
        this.$btnDelete = $('#btn-delete-quiz');
        this.$btnBuilder = $('#btn-builder-quiz');
        this.$btnPreview = $('#btn-preview-quiz');
        this.$btnEnable = $('#btn-enable-quiz');
        this.$btnDisable = $('#btn-disable-quiz');
    }

    init() {
        this.initEvents();
        console.log("QuizTable initialized");
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
        this.$btnBuilder.prop('disabled', !isSingle);
        this.$btnPreview.prop('disabled', !isSingle);

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
