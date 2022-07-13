import {transformExtent} from 'ol/proj';
import {fromExtent} from 'ol/geom/Polygon';

const getCurrentViewExtentCoordinates = ({map}) => {
  const extent = map.getView().calculateExtent(map.getSize());
  const transformedExtent = transformExtent(extent, 'EPSG:3857', 'EPSG:4326');
  const polygon = fromExtent(transformedExtent);
  return polygon.getCoordinates()[0];
}

window.farm_farmlab = {
  getCurrentViewExtentCoordinates,
}
