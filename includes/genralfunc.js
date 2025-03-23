 function sanitizeURL(inputSelector) {
                let urlInputField = $(inputSelector);
                let urlInput = $.trim(urlInputField.val()); // Trim whitespace

                // Remove http://, https://, and www. from the beginning (case insensitive)
                urlInput = urlInput.replace(/^(https?:\/\/)?(www\.)?/i, '');

                let urlPattern = /^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/; // Allows domain format like example.com

                if (!urlPattern.test(urlInput)) {
                    alert('Please enter a valid domain without http/https (e.g., example.com)');
                    return false; // Prevent form submission if invalid
                }

                // Update the input field value before submitting
                urlInputField.val(urlInput);
                return true; // Return true if validation passes
            }