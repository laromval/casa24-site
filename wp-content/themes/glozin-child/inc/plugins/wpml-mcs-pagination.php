<?php

add_action('admin_footer', 'glozin_child_wpml_tm_pagination_js');
function glozin_child_wpml_tm_pagination_js() {
    if (!is_admin()) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'tm/menu/main.php') return;

    $extra_options = array(500, 1000, 2000, 5000, 10000);
?>
<script>
(function() {
    var extraOptions = <?php echo json_encode($extra_options); ?>;
    var label = ' / \u0441\u0442\u0440\u0430\u043d\u0438\u0446\u0430';

    function addOptionsToSelect(select) {
        if (!select) return;

        var hasAll = true;
        extraOptions.forEach(function(val) {
            if (!select.querySelector('option[value="' + val + '"]')) hasAll = false;
        });
        if (hasAll) {
            var cv = select.value || select.getAttribute('value');
            if (cv && extraOptions.indexOf(parseInt(cv, 10)) !== -1) {
                select.value = cv;
            }
            return;
        }

        extraOptions.forEach(function(val) {
            if (select.querySelector('option[value="' + val + '"]')) return;
            var opt = document.createElement('option');
            opt.value = String(val);
            opt.textContent = val + label;
            select.appendChild(opt);
        });

        var cv2 = select.value || select.getAttribute('value');
        if (cv2 && extraOptions.indexOf(parseInt(cv2, 10)) !== -1) {
            select.value = cv2;
        }
    }

    function processAllSelects() {
        document.querySelectorAll('.pagination-buttons .select-dropdown select').forEach(addOptionsToSelect);
    }

    var observer = new MutationObserver(function() {
        processAllSelects();
    });

    function start() {
        processAllSelects();
        observer.observe(document.getElementById('wpml-dashboard') || document.body, {
            childList: true,
            subtree: true
        });
    }

    var t = 0;
    function tryStart() {
        var el = document.getElementById('wpml-dashboard');
        if (el) {
            start();
        } else if (t < 20) {
            t++;
            setTimeout(tryStart, 500);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { setTimeout(tryStart, 500); });
    } else {
        tryStart();
    }
})();
</script>
<?php
}
