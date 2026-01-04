        </div><!-- /.content-area -->
    </div><!-- /.main-content -->
    
    <script src="assets/app.js"></script>
    
    <!-- HugeRTE Rich Text Editor -->
    <script src="https://cdn.jsdelivr.net/npm/hugerte@1/hugerte.min.js"></script>
    <script>
        // Initialize HugeRTE for all text fields
        if (typeof hugerte !== 'undefined') {
            hugerte.init({
                selector: 'textarea.hugerte-editor',
                skin: 'oxide-dark',
                content_css: 'dark',
                height: 400,
                menubar: false,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | ' +
                    'bold italic forecolor | alignleft aligncenter ' +
                    'alignright alignjustify | bullist numlist outdent indent | ' +
                    'image | removeformat | help',
                content_style: 'body { font-family: Inter, -apple-system, BlinkMacSystemFont, sans-serif; font-size: 14px; color: #e4e4e7; background: #09090b; }',
                promotion: false,
                
                // URL handling - prevent conversion to relative URLs
                relative_urls: false,
                remove_script_host: false,
                convert_urls: false,
                
                // Image upload configuration
                images_upload_url: 'actions.php?action=upload_editor_image',
                automatic_uploads: true,
                images_reuse_filename: false,
                file_picker_types: 'image',
                
                // Custom upload handler with better error handling
                images_upload_handler: function (blobInfo, progress) {
                    return new Promise(function (resolve, reject) {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', 'actions.php?action=upload_editor_image');
                        
                        xhr.upload.onprogress = function (e) {
                            progress(e.loaded / e.total * 100);
                        };
                        
                        xhr.onload = function () {
                            if (xhr.status === 403) {
                                reject({ message: 'HTTP Error: ' + xhr.status, remove: true });
                                return;
                            }
                            
                            if (xhr.status < 200 || xhr.status >= 300) {
                                reject('HTTP Error: ' + xhr.status);
                                return;
                            }
                            
                            const json = JSON.parse(xhr.responseText);
                            
                            if (!json || typeof json.location != 'string') {
                                reject('Invalid JSON: ' + xhr.responseText);
                                return;
                            }
                            
                            resolve(json.location);
                        };
                        
                        xhr.onerror = function () {
                            reject('Image upload failed due to a XHR Transport error. Code: ' + xhr.status);
                        };
                        
                        const formData = new FormData();
                        formData.append('file', blobInfo.blob(), blobInfo.filename());
                        
                        xhr.send(formData);
                    });
                }
            });
        }
    </script>
</body>
</html>
