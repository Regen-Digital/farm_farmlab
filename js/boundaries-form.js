(function (Drupal, drupalSettings) {

  Drupal.behaviors.farm_farmlab_boundaries = {

    // Map layers.
    layer: null,

    attach: function (context, settings) {

      // Create source geojson layer.
      const instance = window.farmOS.map.instances[0];
      const url = new window.URL('/farmlab/boundaries/geojson', window.location.origin + drupalSettings.path.baseUrl);
      let geoJsonUrlOpts = {
        title: 'Boundaries',
        url,
        color: 'blue',
        visible: true,
      };
      this.layer = instance.addLayer('geojson', geoJsonUrlOpts);

      // Zoom to vectors.
      this.layer.getSource().on('change', function () {
        instance.zoomToVectors();
      });
    }

  };
}(Drupal, drupalSettings));
