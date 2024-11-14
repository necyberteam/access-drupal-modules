(function (Drupal, drupalSettings) {

  'use strict';

  /**
   * Attaches the JS test behavior to weight div.
   */
  Drupal.behaviors.accessMiscProgram = {
    attach: function (context, settings) {
      var currentMenu = drupalSettings.access_misc.current_menu;
      var peopleLink = document.querySelectorAll('[data-drupal-link-system-path="people"]');
      peopleLink.forEach(function (link) {
        if (!link.href.includes('?')) {
          link.href = link.href + '?facets_query=&f%5B0%5D=program%3A' + currentMenu;
        }
      });
    }
  };
})(Drupal, drupalSettings);
