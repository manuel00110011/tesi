
<div class="text-center mb-3">
    <?php if (!empty($successoFoto)): ?>
        <div class="alert alert-success alert-sm p-2 mb-2" id="successMessage">
            <small><?= htmlspecialchars($successoFoto) ?></small>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($erroreFoto)): ?>
        <div class="alert alert-danger alert-sm p-2 mb-2" id="errorMessage">
            <small><?= htmlspecialchars($erroreFoto) ?></small>
        </div>
    <?php endif; ?>
    
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('fileInput').click()">
        Cambia Foto
    </button>
    
    <!-- Form nascosto -->
    <form method="post" enctype="multipart/form-data" id="fotoForm" style="display: none;">
        <input type="file" 
               id="fileInput" 
               name="nuova_foto" 
               accept="image/*" 
               onchange="submitFoto()"
               style="display: none;">
        <input type="hidden" name="carica_foto" value="1">
    </form>
</div>

<style>
.alert-sm {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

.btn-sm {
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 15px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const successMsg = document.getElementById('successMessage');
    const errorMsg = document.getElementById('errorMessage');
    
    if (successMsg) {
        setTimeout(() => {
            successMsg.style.transition = 'opacity 0.5s';
            successMsg.style.opacity = '0';
            setTimeout(() => successMsg.remove(), 500);
        }, 3000);
    }
    
    if (errorMsg) {
        setTimeout(() => {
            errorMsg.style.transition = 'opacity 0.5s';
            errorMsg.style.opacity = '0';
            setTimeout(() => errorMsg.remove(), 500);
        }, 5000);
    }
});

function submitFoto() {
    const file = document.getElementById('fileInput').files[0];
    if (file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 5 * 1024 * 1024;
        
        if (!allowedTypes.includes(file.type)) {
            alert('Formato non supportato. Usa JPG, PNG, GIF o WEBP.');
            document.getElementById('fileInput').value = '';
            return;
        }
        
        if (file.size > maxSize) {
            alert('File troppo grande. Max 5MB.');
            document.getElementById('fileInput').value = '';
            return;
        }
        
        if (confirm('Vuoi caricare questa foto come nuova immagine profilo?')) {
            const btn = document.querySelector('button[onclick="document.getElementById(\'fileInput\').click()"]');
            btn.innerHTML = 'Caricamento...';
            btn.disabled = true;
            document.getElementById('fotoForm').submit();
        } else {
            document.getElementById('fileInput').value = '';
        }
    }
}
</script>