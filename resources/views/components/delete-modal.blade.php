<div class="modal-overlay" id="deleteConfirmModal" data-modal-overlay>
    <div class="modal-container modal-container-sm" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
        <div class="modal-header modal-header-danger">
            <div class="modal-title-copy">
                <h3 id="deleteConfirmTitle">Deactivate Data?</h3>
                <p>Data yang dipilih akan dinonaktifkan.</p>
            </div>
            <button class="btn-close" type="button" data-modal-dismiss="deleteConfirmModal" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="modal-body">
            <p class="modal-confirm-copy" id="deleteConfirmMessage">Are you sure you want to continue?</p>
        </div>

        <form id="deleteConfirmForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="modal-footer">
                <button class="btn-cancel" type="button" data-modal-dismiss="deleteConfirmModal">Cancel</button>
                <button class="btn-save btn-danger-action" type="submit" id="deleteConfirmSubmit">Deactivate</button>
            </div>
        </form>
    </div>
</div>
