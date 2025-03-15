
document.addEventListener('DOMContentLoaded', function() {
    setupDropzone();
    console.log('Dropzone initialized');
});

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

    // Handle dropped files
    dropzone.addEventListener('drop', function(e) {
        const file = e.dataTransfer.files[0];
        handleFileSelect(file);
    }, false);

    function handleFileSelect(file) {
        // Check file type
        const validTypes = [
            'text/csv',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];
        
        if (!validTypes.includes(file.type) && 
            !file.name.endsWith('.csv') && 
            !file.name.endsWith('.xlsx')) {
            alert('Пожалуйста, загрузите файл CSV или XLSX');
            return;
        }

        // Update UI
        fileInfo.classList.remove('d-none');
        fileName.textContent = file.name;
        fileSize.textContent = `${(file.size / 1024).toFixed(2)} KB`;
        submitBtn.disabled = false;
        progressBar.style.width = '0%';

        // Update file input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;

        // Setup form submission with progress
        const form = document.getElementById('upload-form');
        form.onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                }
            };

            xhr.onload = function() {
                if (xhr.status === 302 || xhr.status === 200) {
                    window.location.href = 'results.php';
                } else {
                    alert('Ошибка при загрузке файла');
                }
            };

            xhr.send(formData);
            submitBtn.disabled = true;
        };ataTransfer.files;
    }
}
