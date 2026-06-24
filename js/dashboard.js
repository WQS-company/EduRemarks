/**
 * EduRemarks Dashboard Global JS
 * Handles shared dashboard interactions like school switching.
 */

const Dashboard = {
    /**
     * Switch the active school environment
     * @param {number} schoolId 
     */
    switchSchool: function (schoolId) {
        if (typeof Spinner !== 'undefined') {
            Spinner.show('Switching Environment...');
        }

        const path = (window.EduRemarks && window.EduRemarks.pathPrefix) ? window.EduRemarks.pathPrefix : "";

        fetch(path + 'ajax/switch_school.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'school_id=' + schoolId
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    if (typeof Notif !== 'undefined') {
                        Notif.show(data.message, 'error');
                    } else {
                        alert(data.message);
                    }
                    if (typeof Spinner !== 'undefined') {
                        Spinner.hide();
                    }
                }
            })
            .catch(error => {
                console.error('Error switching school:', error);
                if (typeof Spinner !== 'undefined') {
                    Spinner.hide();
                }
            });
    }
};

// Global alias for compatibility with existing onclick handlers
window.switchSchool = Dashboard.switchSchool;
