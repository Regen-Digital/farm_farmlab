(function (Drupal) {

  Drupal.behaviors.farm_farmlab_cadastral = {
    // Map layers.
    layer: null,
    selectedLayer: null,

    attach: function (context, settings) {

      // Disable submit button.
      this.setSubmitButtonState(false);

      // Change Load cadastrals button behavior.
      context.getElementById('load-cadastrals').addEventListener("click", (event) => {
        event.preventDefault();
        this.updateLayer();
      });

      // Change Load cadastrals button behavior.
      context.getElementById('farm-farmlab-cadastral-form').addEventListener("submit", (event) => {
        this.submit();
      });
    },
    createLayer: function (instance, url) {

      // Create source geojson layer.
      let geoJsonUrlOpts = {
        title: 'Cadastral',
        url,
        color: 'blue',
        visible: true,
      };
      this.layer = instance.addLayer('geojson', geoJsonUrlOpts);

      // Create selected geojson layer.
      let selectedLayerOpts = {
        title: 'Selected Cadastrals',
        geojson: {type: 'FeatureCollection', features: []},
        color: 'orange',
        visible: true,
      };
      this.selectedLayer = instance.addLayer('geojson', selectedLayerOpts);

      // Loadstart callback.
      this.layer.getSource().on('featuresloadstart', () => {
        document.getElementById('load-cadastrals').setAttribute('disabled', true);
      });

      // Loadend callback.
      this.layer.getSource().on('featuresloadend', () => {
        document.getElementById('load-cadastrals').removeAttribute('disabled');

        // Check the feature count.
        const features = this.layer.getSource().getFeatures();
        if (features.length === 0) {
          const messages = new Drupal.Message();
          messages.add('No cadastrals found in the current view. Please try another area.', {type: 'warning'});
        }
      });

      // Loaderror callback.
      this.layer.getSource().on('featuresloaderror', () => {
        document.getElementById('load-cadastrals').removeAttribute('disabled');
        const messages = new Drupal.Message();
        messages.add('Error loading cadastrals. Please try a smaller area.', {type: 'error'});
      });

      // Helper function to build the feature name as a link.
      const featureName = function (feature) {
        // Bail if either the name or url aren't defined.
        const name = feature.get('name');
        const url = feature.get('url');
        if (name === undefined || url === undefined) {
          return name;
        }
        // Build a link with the url and name.
        return `<a href="${url}">${name}</a>`;
      }

      // Create a popup and add it to the instance for future reference.
      instance.map.removeOverlay(instance.popup);
      instance.popup = instance.addPopup(function (event) {
        var content = '';
        var feature = instance.map.forEachFeatureAtPixel(event.pixel, function(feature, layer) { return feature; });
        if (feature) {

          // A popup name is required.
          var name = featureName(feature) || '';
          if (name !== '') {

            // Get the description and measurement.
            var featureId = feature.getId();
            var description = feature.get('description') || '';

            // Build the name header.
            const nameHeader = document.createElement('h4');
            nameHeader.classList.add('ol-popup-name');
            nameHeader.innerHTML = name;
            const checkbox = Drupal.behaviors.farm_farmlab_cadastral.createCheckbox(featureId);
            nameHeader.prepend(checkbox);

            // Build the description div.
            const descriptionDiv = document.createElement('div');
            descriptionDiv.classList.add('ol-popup-description');
            const ul = document.createElement('ul');
            const descriptionKeys = {
              lotNumber: Drupal.t('Lot number'),
              planNumber: Drupal.t('Plan number'),
              councilName: Drupal.t('Council name'),
            };
            for (const [key, label] of Object.entries(descriptionKeys)) {
              if (feature.get(key)) {
                const li = document.createElement('li');
                li.innerHTML = `${label}: ${feature.get(key)}`;
                ul.append(li);
              }
            }
            descriptionDiv.append(ul);

            // Create popup content.
            content = nameHeader.outerHTML + descriptionDiv.outerHTML;

            // Select the feature.
            Drupal.behaviors.farm_farmlab_cadastral.updateSelection(featureId, true);
          }
        }
        return content;
      });
    },
    updateLayer: function () {

      // Build cadastral geojson URL.
      const instance = window.farmOS.map.instances[0];
      const url = new window.URL('/farmlab/cadastral/geojson', window.location.origin + drupalSettings.path.baseUrl);

      // Append each coordinate.
      const coordinates = window.farm_farmlab.getCurrentViewExtentCoordinates(instance);
      coordinates.forEach(value => {
        url.searchParams.append('coordinates[]', value)
      })

      // Clear messages.
      const messages = new Drupal.Message();
      messages.clear();

      // Create or update the layer.
      if (!this.layer) {
        this.createLayer(instance, url);
      }
      else {
        this.layer.getSource().setUrl(url);
        this.layer.getSource().refresh();
      }
    },
    updateSelection: (featureId, selected) => {

      // Save layers.
      const sourceLayer = Drupal.behaviors.farm_farmlab_cadastral.layer;
      const selectedLayer = Drupal.behaviors.farm_farmlab_cadastral.selectedLayer;

      // Ensure the feature cadastral ID is set.
      const sourceFeature = sourceLayer.getSource().getFeatureById(featureId);
      const selectedFeature = selectedLayer.getSource().getFeatureById(featureId);
      if (!sourceFeature) {
        return;
      }

      // If not checked, unselect the cadastral.
      const tableBody = document.querySelector('table#selected-cadastrals tbody');
      const row = tableBody.querySelector(`tr[data-cadastral-id="${featureId}"]`);
      if (!selected) {

        // Remove the row.
        if (row) {
          row.remove();
        }
        // Remove the selected feature.
        if (selectedFeature) {
          selectedLayer.getSource().removeFeature(selectedFeature);
        }
      }

      // Update other checkboxes for the same cadastral.
      document.querySelectorAll(`input[data-cadastral-id="${featureId}"]`).forEach((input) => {
        input.checked = selected;
      });

      // If checked, select the cadastral.
      if (!row && selected) {

        // Clone the feature to the selected layer.
        const clonedFeature = sourceFeature.clone();
        clonedFeature.setId(featureId);
        selectedLayer.getSource().addFeature(clonedFeature);

        // Create a new row.
        const newRow = document.createElement('tr');
        newRow.setAttribute('data-cadastral-id', featureId);
        const featureValues = {
          selected: Drupal.behaviors.farm_farmlab_cadastral.createCheckbox(featureId).outerHTML,
          name: sourceFeature.get('name'),
          lotNumber: sourceFeature.get('lotNumber'),
          planNumber: sourceFeature.get('planNumber'),
        };

        // Add a cell for each table column.
        for (const [key, value] of Object.entries(featureValues)) {
          const cell = document.createElement('td');
          cell.classList.add(key);
          cell.innerHTML = value;
          newRow.appendChild(cell);
        }

        // Finally prepend the row to the table.
        tableBody.prepend(newRow);
      }

      // Update submit button state.
      const selectedRows = tableBody.querySelectorAll('tr[data-cadastral-id]');
      Drupal.behaviors.farm_farmlab_cadastral.setSubmitButtonState(!!selectedRows.length);
    },
    createCheckbox: (id) => {

      // Create an input element.
      const input = document.createElement('input');
      input.setAttribute('type', 'checkbox');
      input.setAttribute('data-cadastral-id', id);
      input.setAttribute('onchange', 'Drupal.behaviors.farm_farmlab_cadastral.checkboxChange(this)');

      // Add classes.
      input.classList.add('form-boolean', 'form-boolean--type-checkbox', 'cadastral');

      // Set checked state to true.
      input.defaultChecked = true;

      return input;
    },
    checkboxChange: (element) => {
      const featureId = element.getAttribute('data-cadastral-id');
      const selected = element.checked;
      Drupal.behaviors.farm_farmlab_cadastral.updateSelection(featureId, selected);
    },
    setSubmitButtonState: (enabled) => {

      // Set state.
      const button = document.getElementById('edit-submit');
      button.disabled = !enabled;

      // Update class list.
      const operation = !enabled ? 'add' : 'remove';
      button.classList[operation]('is-disabled');
    },
    submit: () => {

      // Don't submit if there are no selected layers.
      if (!Drupal.behaviors.farm_farmlab_cadastral.selectedLayer) {
        return;
      }

      let data = [];
      Drupal.behaviors.farm_farmlab_cadastral.selectedLayer.getSource().getFeatures().forEach((feature) => {
        let properties = feature.getProperties();
        delete properties.geometry;
        data.push({
          id: feature.getId(),
          geometry: window.farm_farmlab.getFeaturesWKT([feature], window.farmOS.map.projection),
          properties: properties,
        });
      });

      // Update hidden input field.
      document.querySelector('input[name="cadastral-data"]').value = JSON.stringify(data);
    }

  };
}(Drupal));
