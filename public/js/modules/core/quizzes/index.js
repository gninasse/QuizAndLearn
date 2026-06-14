import { QuizTable } from './QuizTable.js';
import { QuizForm } from './QuizForm.js';
import { QuizActions } from './QuizActions.js';

$(document).ready(function () {
    console.log("Initializing Quizzes Module (Native ES)...");

    // Setup CSRF for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Components
    const quizTable = new QuizTable('#quizzes-table');
    quizTable.init();

    const quizForm = new QuizForm('#quizModal', '#quizForm', quizTable);
    const quizActions = new QuizActions(quizTable, quizForm);

    // Initialize Global Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
