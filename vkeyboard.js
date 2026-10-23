/**
 * Mark login fields for GreyWyvern VKI without clobbering Elastic form-control.
 */
(function () {
  function markKeyboardInputs() {
    var lang = (window.rcmail && rcmail.env && rcmail.env.vklang) || '';
    if (lang && !/^[a-z]{2}([_-][A-Za-z0-9]+)*$/.test(lang)) {
      lang = '';
    }
    var $fields = $('#login-form input[type="text"], #login-form input[type="password"],'
      + ' #rcmloginuser, #rcmloginpwd');

    if (!$fields.length) {
      $fields = $('body.task-login input[type="text"], body.task-login input[type="password"]');
    }

    $fields.each(function () {
      var $el = $(this);
      if (lang) {
        $el.attr('lang', lang);
      }
      // Never replace class — Elastic needs form-control / form-control-lg.
      $el.addClass('keyboardInput');
      $el.parent().addClass('vkeyboard-field');
    });
  }

  $(markKeyboardInputs);
})();
