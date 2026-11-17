/**
 * Pure, browser-independent millimetre geometry functions for ID Studio.
 * Keep numerical checks here so pointer, keyboard, and toolbar actions behave alike.
 */
export const roundMM = value => Math.round(value * 100) / 100;
export const clampMM = (value, min, max) => Math.min(max, Math.max(min, value));

export function moveRect(rect, dx, dy, width, height, grid = 0) {
  const toGrid = v => grid > 0 ? Math.round(v / grid) * grid : v;
  return {
    x: roundMM(clampMM(toGrid(rect.x + dx), 0, width - rect.w)),
    y: roundMM(clampMM(toGrid(rect.y + dy), 0, height - rect.h)),
  };
}

export function resizeRect(rect, dw, dh, width, height, grid = 0) {
  const toGrid = v => grid > 0 ? Math.round(v / grid) * grid : v;
  return {
    w: roundMM(clampMM(toGrid(rect.w + dw), 2, width - rect.x)),
    h: roundMM(clampMM(toGrid(rect.h + dh), 2, height - rect.y)),
  };
}

export function alignRect(rect, mode, width, height) {
  const positions = {
    left: {x: 0}, hcenter: {x: (width - rect.w) / 2}, right: {x: width - rect.w},
    top: {y: 0}, vcenter: {y: (height - rect.h) / 2}, bottom: {y: height - rect.h},
  };
  if (!(mode in positions)) throw new RangeError('Unknown alignment mode');
  const result = {};
  for (const [axis, value] of Object.entries(positions[mode])) result[axis] = roundMM(value);
  return result;
}

/** Stable ordering; no duplicate/overflowing z-index values, no mutation of inputs. */
export function reorderLayers(elements, id, action) {
  const ordered = elements.map((e, index) => ({...e, __index: index}))
    .sort((a,b) => a.z - b.z || a.__index - b.__index);
  const i = ordered.findIndex(e => e.id === id);
  if (i < 0 || ordered[i].locked) return elements.map(e => ({...e}));
  let destination = i;
  if (action === 'up') destination = Math.min(i + 1, ordered.length - 1);
  else if (action === 'down') destination = Math.max(i - 1, 0);
  else if (action === 'front') destination = ordered.length - 1;
  else if (action === 'back') destination = 0;
  else throw new RangeError('Unknown layer action');
  const [selected] = ordered.splice(i, 1);
  ordered.splice(destination, 0, selected);
  return ordered.map((e, index) => {
    const {__index, ...original} = e;
    return {...original, z: index + 1};
  });
}

/**
 * Smart snap targets: card edges/centers and other field edges/centers.
 * Each invocation is O(number of other elements) with a constant-size set of anchors.
 */
export function findAlignmentGuides(rect, others, width, height, tolerance = 0.65) {
  const candidatesX = [0, width / 2, width];
  const candidatesY = [0, height / 2, height];
  for (const e of others) {
    candidatesX.push(e.x, e.x + e.w / 2, e.x + e.w);
    candidatesY.push(e.y, e.y + e.h / 2, e.y + e.h);
  }
  function best(anchors, targets) {
    let result = null;
    for (const target of targets) {
      for (const anchor of anchors) {
        const offset = target - anchor;
        if (Math.abs(offset) <= tolerance && (result === null || Math.abs(offset) < Math.abs(result.offset))) {
          result = {offset, at: roundMM(target)};
        }
      }
    }
    return result;
  }
  const xGuide = best([rect.x, rect.x + rect.w / 2, rect.x + rect.w], candidatesX);
  const yGuide = best([rect.y, rect.y + rect.h / 2, rect.y + rect.h], candidatesY);
  const x = xGuide ? roundMM(clampMM(rect.x + xGuide.offset, 0, width - rect.w)) : rect.x;
  const y = yGuide ? roundMM(clampMM(rect.y + yGuide.offset, 0, height - rect.h)) : rect.y;
  return {x,y, vertical: xGuide?.at ?? null, horizontal: yGuide?.at ?? null};
}
