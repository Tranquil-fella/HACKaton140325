/**
 * Main JavaScript file for product description analyzer
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dropzone functionality
    setupDropzone();
});

/**
 * Setup the dropzone for file uploads
 */
function setupDropzone() {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file-input');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.querySelector('.file-name');
    const fileSize = document.querySelector('.file-size');
    const submitBtn = document.getElementById('submit-btn');
    const progressBar = document.querySelector('.progress-bar');
    
    if (!dropzone || !fileInput) return;
    
    // Click on dropzone to trigger file input
    dropzone.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Handle file selection
    fileInput.addEventListener('change', function() {
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            handleFileSelect(file);
        }
    });
    
    // Handle drag and drop events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Add visual feedback for drag events
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, function() {
            dropzone.classList.add('active');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, function() {
            dropzone.classList.remove('active');
        }, false);
    });
    
    // Handle file drop
    dropzone.addEventListener('drop', function(e) {
        const file = e.dataTransfer.files[0];
        if (file && (file.name.toLowerCase().endsWith('.csv') || file.name.toLowerCase().endsWith('.xlsx'))) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect(file);
        } else {
            alert('Пожалуйста, загрузите файл в формате CSV или XLSX');
        }
    }, false);
    
    /**
     * Handle file selection
     * @param {File} file The selected file
     */
    function handleFileSelect(file) {
        // Check file type
        const fileExt = file.name.split('.').pop().toLowerCase();
        if (fileExt !== 'csv' && fileExt !== 'xlsx') {
            alert('Пожалуйста, загрузите файл в формате CSV или XLSX');
            return;
        }
        
        // Update file info
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.classList.remove('d-none');
        
        // Simulate progress (for visual feedback)
        let progress = 0;
        const interval = setInterval(function() {
            progress += 10;
            progressBar.style.width = progress + '%';
            
            if (progress >= 100) {
                clearInterval(interval);
                submitBtn.disabled = false;
            }
        }, 100);
    }
    
    /**
     * Format file size in human-readable format
     * @param {number} bytes File size in bytes
     * @return {string} Formatted file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}