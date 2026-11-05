{{-- Confirmation pop-up shared by the whole app. resources/js/confirm-dialog.js fills it in and opens it. --}}
<dialog id="confirm-dialog" class="confirm-dialog" role="alertdialog" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
    <form method="dialog" class="confirm-dialog-panel">
        <div class="confirm-dialog-icon" aria-hidden="true">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        </div>
        <div>
            <h2 id="confirm-dialog-title" class="confirm-dialog-title"></h2>
            <p id="confirm-dialog-message" class="confirm-dialog-message"></p>
        </div>
        <div class="confirm-dialog-actions">
            {{-- Cancel takes focus, so pressing Enter by reflex doesn't run the action --}}
            <button type="submit" value="cancel" class="ui-btn ui-btn-secondary" autofocus>Cancel</button>
            <button type="submit" value="confirm" class="ui-btn ui-btn-danger" data-confirm-button></button>
        </div>
    </form>
</dialog>
