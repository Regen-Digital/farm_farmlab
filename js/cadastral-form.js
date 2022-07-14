(function (Drupal) {

  Drupal.behaviors.farm_farmlab_cadastral = {
    layer: null,

    attach: function (context, settings) {

      // Change Load cadastrals button behavior.
      context.getElementById('load-cadastrals').addEventListener("click", (event) => {
        event.preventDefault();
        this.updateLayer();
      })
    },
    createLayer: function (map, url) {

      // Creat geojson layer.
      let geoJsonUrlOpts = {
        title: 'Cadastral', // defaults to 'geojson'
        url,
        color: 'blue', // defaults to 'orange'
        visible: true, // defaults to true
      };
      this.layer = map.addLayer('geojson', geoJsonUrlOpts);

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
    },
    updateLayer: function () {

      // Build cadastral geojson URL.
      const map = window.farmOS.map.instances[0];
      const url = new window.URL('/farmlab/cadastral/geojson', window.location.origin + drupalSettings.path.baseUrl);

      // Append each coordinate.
      const coordinates = window.farm_farmlab.getCurrentViewExtentCoordinates(map);
      coordinates.forEach(value => {
        url.searchParams.append('coordinates[]', value)
      })

      // Clear messages.
      const messages = new Drupal.Message();
      messages.clear();

      // Create or update the layer.
      if (!this.layer) {
        this.createLayer(map, url);
      }
      else {
        this.layer.getSource().setUrl(url);
        this.layer.getSource().refresh();
      }
    }

  };
}(Drupal));
