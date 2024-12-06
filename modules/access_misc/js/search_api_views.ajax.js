/**
 * @file
 * Search API Views AJAX.
 */

(function ($, Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Trigger views AJAX refresh on click event.
   */
  Drupal.behaviors.searchApiViewsAjax = {
    attach: function (context, settings) {
      // Get the View for the current page.
      var view, currentDomId, viewPath;

      const searchQueryKey = settings.search_api_views_ajax.settings.value;
      if (settings.views && settings.views.ajaxViews) {
        $.each(settings.views.ajaxViews, function (domId, viewSettings) {
          currentDomId = viewSettings.view_dom_id;
          viewPath = viewSettings.view_path;
          view = $('.js-view-dom-id-' + currentDomId);
        });
      }

      if (!view || view.length != 1) {
        return;
      }

      // Update search query on click/submit
      const searchExposed = $(context).find('input').hasClass('form-control');

      if (moduleConfigEnabled() && searchExposed) {
        $('input.form-control').each(function () {

          let formInput = $(this);
          if (!formInput) {
            return;
          }

          // Input name needs to come from view as this is configurable.
          let searchQuery = formInput.val();
          // We need to encode special characters such as "" before sharing.
          let encodedQuery = encodeURI(searchQuery);
          let location = window.location.origin + window.location.pathname;
          updateSearchApiQueryString(location, currentDomId, viewPath, encodedQuery, searchQueryKey);

        });
      }

    }
  };

  /**
   * Updates the Search API Query String
   *
   * @param {String} href
   *   The href to update.
   * @param {String} currentDomId
   *   The current dom id taken from viewSettings.view_dom_id
   * @param {String} viewPath
   *   The current view_path taken from viewSettings.view_path
   * @param {String} searchQuery
   *   The current searchQuery taken from the search input value
   * @param {String} searchQueryKey
   *   The current views searchQuery passed via hook_views_pre_render
   * @return {void}
   *   A string containing the baseUrl updated with params object
   */
  const updateSearchApiQueryString = function (href, currentDomId, viewPath, searchQuery, searchQueryKey) {

    // Update View.
    let viewsParameters = Drupal.Views.parseQueryString(href);
    let keyValueLength = viewsParameters[searchQueryKey] ? viewsParameters[searchQueryKey].length : 0;
    let sq;
    let newParams = {};

    // If search query value already set.
    if (keyValueLength > 0) {
      newParams[searchQueryKey] = searchQuery;
      // Lets update href with new params object to get sq.
      sq = getUriWithParam(href, newParams);
    } else {
      // If not we can create a new sq with just does not already have keyword.
      sq = href + '?' + searchQueryKey + '=' + searchQuery;
    }

    const searchApiViewsAjaxState = sessionStorage.getItem('searchApiViewsAjax');

    // We need a way of regulate updating the browser history to ensure we only
    // update it once. We are using session storage to help with this.
    if (!searchApiViewsAjaxState) {
      sessionStorage.setItem('searchApiViewsAjax', sq);
    }
    else if (searchApiViewsAjaxState != sq) {
      sessionStorage.setItem('searchApiViewsAjax', sq);

      if (Drupal.views.instances['views_dom_id:' + currentDomId] !== undefined) {
        let viewsAjaxSettings = Drupal.views.instances['views_dom_id:' + currentDomId].element_settings;
        // Update viewsAjaxSettings.url to reflect new sq
        viewsAjaxSettings.url = viewPath + '?search=' + sq;

        // Update browser history using history api.
        if (searchQuery) {
          window.history.pushState(newParams, document.title + ' > ' + searchQuery, sq);
        }
      }
    }

    // Update url.
    if (window.searchApiViewsAjaxHistory === undefined) {
      window.searchApiViewsAjaxHistory = true;

      // Only add this once otherwise back button reloads the page multiple
      // times. Perhaps we can replace window.searchApiViewsAjaxHistory with a
      // local scoped variable
      window.addEventListener("popstate", function (event) {
        // Not sure where event.isTrusted is set but sounds good!
        // Works, but sometimes this does not seem to fire on back button?
        if (window.searchApiViewsAjaxHistory && event.isTrusted) {
          $('.js-view-dom-id-' + currentDomId).trigger('RefreshView');
        }

      });

    }

  };

  /**
   * Utility function to update a query string param.
   *
   * @param {string} baseUrl
   *   The base url. See https://stackoverflow.com/a/65285991/3592441
   * @param {object} params
   *   The params object
   * @return {string}
   *   A string containing the baseUrl updated with params object
   *
   * examples:
   *
   * getUriWithParam("https://example.com", { foo: "bar" });
   * https://example.com/?foo=bar
   *
   * getUriWithParam("https://example.com/slug#hash", { foo: "bar" })
   * https://example.com/slug?foo=bar#hash
   *
   * getUriWithParam("https://example.com?bar=baz", { foo: "bar" })
   * https://example.com/?bar=baz&foo=bar
   *
   * getUriWithParam("https://example.com?foo=bar&bar=baz", { foo: "baz" })
   * https://example.com/?foo=baz&bar=baz
   *
   */
  const getUriWithParam = (baseUrl, params) => {
    const Url = new URL(baseUrl);
    const urlParams = new URLSearchParams(Url.search);
    for (const key in params) {
      if (params[key] !== undefined) {
        urlParams.set(key, params[key]);
      }
    }
    Url.search = urlParams.toString();
    return Url.toString();
  };

  /**
   * Helper function to determine if module is enabled
   *
   * @return {boolean}
   *   A boolean dependent on search_api_views_ajax.settings.enabled
   */
  const moduleConfigEnabled = function () {
    let settings = drupalSettings;
    let update = false;
    if (settings.search_api_views_ajax.settings.enabled) {
      update = true;
    }
    return update;
  };

})(jQuery, Drupal, once, drupalSettings);

