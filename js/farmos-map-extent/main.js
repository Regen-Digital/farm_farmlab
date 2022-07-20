import { transformExtent } from 'ol/proj';
import { getTopLeft, getBottomLeft, getTopRight, getBottomRight } from 'ol/extent';
import { WKT } from 'ol/format';

const getCurrentViewExtentCoordinates = ({map}) => {
  const extent = map.getView().calculateExtent(map.getSize());
  const transformedExtent = transformExtent(extent, 'EPSG:3857', 'EPSG:4326');
  const tl = getTopLeft(transformedExtent);
  return [
    tl,
    getBottomLeft(transformedExtent),
    getBottomRight(transformedExtent),
    getTopRight(transformedExtent),
    tl
  ];
}

const getFeaturesWKT = (features, projection) => {
  return new WKT().writeFeatures(features, projection);
}

window.farm_farmlab = {
  getCurrentViewExtentCoordinates,
  getFeaturesWKT,
}
