import { ArticleTable } from './ArticleTable.js';
import { ArticleForm } from './ArticleForm.js';
import { ArticleActions } from './ArticleActions.js';

$(document).ready(function () {
    console.log("Initializing Articles Module (Native ES)...");

    // Setup CSRF for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Components
    const articleTable = new ArticleTable('#articles-table');
    articleTable.init();

    const articleForm = new ArticleForm('#articleModal', '#articleForm', articleTable);
    const articleActions = new ArticleActions(articleTable, articleForm);

    // Initialize Global Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
