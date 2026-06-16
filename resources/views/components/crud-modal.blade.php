<div class="modal-overlay" id="crudModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Data Baru</h3>
            <button class="btn-close" onclick="closeModal('crudModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="modalFields"></div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('crudModal')">Batal</button>
            <button class="btn-save" onclick="saveData()">Simpan Data</button>
        </div>
    </div>
</div>
