document.addEventListener('DOMContentLoaded', function() {
    var addButton = document.getElementById('add-script-field');

    if (addButton) {
        addButton.addEventListener('click', function() {
            var scriptFields = document.getElementById('script-fields');
            var newRow = document.createElement('tr');
            newRow.className = 'script-field';
            newRow.innerHTML = '<td><input type="text" class="regular-text" name="cdnjs_script_loader_settings[scripts][]" placeholder="e.g., jquery" /></td>' +
                               '<td><input type="text" class="regular-text" name="cdnjs_script_loader_settings[versions][]" placeholder="e.g., 3.7.1" /></td>' +
                               '<td><input type="text" class="regular-text" name="cdnjs_script_loader_settings[filenames][]" placeholder="e.g., jquery.min.js" /></td>' +
                               '<td><button type="button" class="button remove-script-field">Remove</button></td>';
            scriptFields.appendChild(newRow);

            // Attach event listener to new remove button
            attachRemoveListeners();
        });
    }

    // Attach event listeners to existing remove buttons
    attachRemoveListeners();

    function attachRemoveListeners() {
        var removeButtons = document.querySelectorAll('.remove-script-field');
        removeButtons.forEach(function(button) {
            // Remove any existing listeners to avoid duplicates
            var newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);

            newButton.addEventListener('click', function() {
                var row = this.closest('tr');
                var tbody = row.parentNode;

                // Only remove if there's more than one row
                if (tbody.querySelectorAll('tr').length > 1) {
                    row.remove();
                } else {
                    alert('You must have at least one library entry.');
                }
            });
        });
    }
});
