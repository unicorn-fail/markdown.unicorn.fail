/**
 * @file
 * The demonstration JavaScript used to reformat user input on the fly.
 */
(function ($) {

  var $document = $(document);

  document.addEventListener('DOMContentLoaded', function () {
    var isMac = /^mac/i.test(navigator.platform);

    var $form = $('[data-drupal-selector="markdown-demo"]');
    var $expires = $form.find('.markdown-expires');
    var $parse = $form.find('[data-drupal-selector="edit-parse"]');

    $parse.html($parse.html() + ' <small>| ' + (isMac ? '⌘' : 'Ctrl') + '+Enter</small>');
    $parse.prepend($expires);

    $form.on('submit', function () {
      $parse.prop('disabled', true);
    });

    // Keyboard shortcut for easy parsing.
    $form.on('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && parseInt(e.keyCode, 10) === 13) {
        $form.submit();
      }
    });

    $document.on('click', '.markdown-rendered a:not([target="_blank"])', function (e) {
      var link = e.currentTarget;

      // Link is truly external. Indicate as such.
      if (!Drupal.url.isLocal(link.href)) {
        link.setAttribute('target', '_blank');
        return;
      }

      // Check for local anchors.
      var hash = link.hash && link.hash.replace(/^#/, '');
      var target = hash && (document.getElementById(hash) || document.getElementsByName(hash));
      if (target) {
        return;
      }

      // Otherwise, link is relative and should be ignored.
      e.preventDefault();
      e.stopPropagation();
    });
  });

})(jQuery);
