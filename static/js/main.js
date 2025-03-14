/**
 * Main JavaScript file for product description analyzer
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize dropzone functionality
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file-input');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.querySelector('.file-name');
    const fileSize = document.querySelector('.file-size');
    const progressBar = document.querySelector('.progress-bar');
    const submitBtn = document.getElementById('submit-btn');
    
    if (dropzone) {
        // Setup drag and drop events
        dropzone.addEventListener('click', function() {
            fileInput.click();
        });
        
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        
        dropzone.addEventListener('dragleave', function() {
            dropzone.classList.remove('dragover');
        });
        
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length) {
                handleFileSelect(fileInput.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            // Validate file type
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (fileExt !== 'csv' && fileExt !== 'xlsx') {
                alert('Поддерживаются только файлы CSV и XLSX');
                return;
            }
            
            // Update file info display
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.classList.remove('d-none');
            
            // Enable submit button
            submitBtn.disabled = false;
            
            // Update form data
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
            
            // Show 100% progress for simplicity
            progressBar.style.width = '100%';
        }
    }
    
    // Handle form submission with progress indicator
    const uploadForm = document.getElementById('upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            // Don't prevent default - we want the form to submit
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Загрузка и анализ...';
        });
    }
    
    // Format file size for display
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
