/**
 * JavaScript chung cho dự án BTN - Kahoot Clone
 */

const KahootApp = {
    /**
     * Tự động ẩn các Alert sau 4 giây
     */
    initAlerts: function() {
        setTimeout(function() {
            $('.alert-auto-dismiss').fadeOut(500, function() {
                $(this).remove();
            });
        }, 4000);
    },

    /**
     * Gửi request JSON tiện lợi
     */
    fetchJson: async function(url, data = null) {
        try {
            const options = {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            };
            if (data) {
                options.method = 'POST';
                options.body = JSON.stringify(data);
            }
            const res = await fetch(url, options);
            return await res.json();
        } catch (err) {
            console.error('Fetch error:', err);
            return null;
        }
    }
};

$(document).ready(function() {
    KahootApp.initAlerts();
});
