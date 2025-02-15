var checkBoxEmail = document.getElementById("edit-field-email-user-value");
if (checkBoxEmail) {
  checkBoxEmail.onclick = function () {
    notes.classList.toggle("hide");
  };
}
(function ($, Drupal, once) {
  "use strict";
  Drupal.behaviors.nodeAddTags = {
    attach: function (context, settings) {
        // Only run this code once
        var options = document.getElementById('edit-field-tags').selectedOptions;
        var set_tid = Array.from(options).map(({ value }) => value);
        const selected = [];

        if (Drupal.ajax) {

          var tagWrapper = document.getElementById("field-tags-replace");
          var suggested_tids = tagWrapper.getAttribute('data-suggest').split(', ');
          var selectElementTags = document.getElementById("edit-field-tags");
          if (suggested_tids != 0) {
            Array.from(suggested_tids).forEach(function (suggested_tid) {
              if (selectElementTags.querySelector('option[value="' + suggested_tid + '"]').selected != true) {
                selected.push(suggested_tid);
              }
            });
          }
        }

        /* Handle tag selection using the node_add_tags view */
        function selectElement(id) {
          let element = document.getElementById(id);
          //element.value = valueToSelect;
          Array.from(element.options).forEach(function (option) {

            // If the option's value is in the selected array, select it
            // Otherwise, deselect it
            if (selected.includes(option.value)) {
              option.selected = true;
            } else {
              option.selected = false;
            }

            // Select all elements with the 'selected' class
            const selectedElements = document.querySelectorAll('.tags-select.selected');
            // Initialize an array to store the text content
            const textArray = [];
            // Loop through each selected element and add its text content to the array
            selectedElements.forEach(element => {
              textArray.push(element.textContent.trim());
            });
            if (textArray.length > 0) {
              var divElement = document.getElementById('tag-suggestions');
              var textTagListing = '';
              for (let textArrayItem of textArray) {
                textTagListing = textTagListing + '<a class="font-normal text-sky-900" href="#tag-' + textArrayItem + '">' + textArrayItem  + '</a>, '
              }
              // Remove the last comma and space
              textTagListing = textTagListing.slice(0, -2);
              // Replace the text content
              divElement.innerHTML = '<div class="bg-slate-100 p-5 my-5"><strong>Selected Tags:</strong><br/>' + textTagListing + '</div>';
            } else {
              // no tags selected
              var divElement = document.getElementById('tag-suggestions');
              divElement.innerHTML = '';
            }
          });
        }

        if (set_tid.length > 0 && set_tid[0] != "_none") {
          selected.push(...set_tid);
          for (let tid of set_tid) {
            const currentButton = document.querySelectorAll("[data-tid='" + tid + "']");
            // If a title is selected then the button won't exist.
            if (currentButton.length > 0) {
              currentButton[0].classList.add("selected");
            }
          }

        }
        selectElement('edit-field-tags');

        const buttonPressed = e => {
          const isSelected = e.target.classList.contains("selected");
          if (isSelected) {
            e.target.classList.remove("selected");
            const index = selected.indexOf(e.target.dataset.tid);
            if (index > -1) { // only splice array when item is found
              selected.splice(index, 1); // 2nd parameter means remove one item only
            }
          } else {
            e.target.classList.add("selected");
            selected.push(e.target.dataset.tid);
          }
          selectElement('edit-field-tags')
        }

        once('nodeAddTags', 'html').forEach(function () {

          const buttons = document.getElementsByClassName('tags-select');
          for (let button of buttons) {
            button.addEventListener("click", buttonPressed, false);
          }
        });


      // Misc tweaks.
      // Select all buttons value="Remove" and change to "X".
      let buttons = document.querySelectorAll('input[value="Remove"]');
      for (let i = 0; i < buttons.length; i++) {
        buttons[i].value = 'X';
      }
      let time = document.querySelectorAll('.path-events input[type="time"]');
      for (let i = 0; i < time.length; i++) {
        time[i].step = '60';
      }

    }
  };
})(jQuery, Drupal, once);
