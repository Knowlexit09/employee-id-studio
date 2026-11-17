import test from 'node:test';
import assert from 'node:assert/strict';
import {roundMM,moveRect,resizeRect,alignRect,reorderLayers,findAlignmentGuides} from '../../resources/js/id-studio-geometry.js';

test('roundMM and keyboard-size nudges stay exact', () => {
  assert.equal(roundMM(1.005), 1);
  const e = {x: 10, y: 8, w: 20, h: 12};
  assert.deepEqual(moveRect(e, 0.1, -0.1, 85.6, 53.98), {x:10.1,y:7.9});
  assert.deepEqual(moveRect(e, 1, 1, 85.6, 53.98), {x:11,y:9});
  assert.deepEqual(e, {x:10,y:8,w:20,h:12}, 'helper must not mutate inputs');
});

test('dragging and resizing honor card boundaries and optional grid', () => {
  const e = {x: 10.2,y: 5.2,w:20,h:12};
  assert.deepEqual(moveRect(e, 100, -100, 85.6, 53.98), {x:65.6,y:0});
  assert.deepEqual(moveRect(e, 1.12, 1.12, 85.6, 53.98, 0.5), {x:11.5,y:6.5});
  assert.deepEqual(resizeRect(e, -99, 99, 85.6,53.98), {w:2,h:48.78});
  assert.deepEqual(resizeRect(e, 1.12, 1.12, 85.6, 53.98, 0.5), {w:21,h:13});
});

test('six card alignments honor portrait and landscape sizes', () => {
  const e={x:3,y:6,w:20,h:10};
  assert.deepEqual(alignRect(e, 'left',54,85.6), {x:0});
  assert.deepEqual(alignRect(e, 'hcenter',54,85.6), {x:17});
  assert.deepEqual(alignRect(e, 'right',54,85.6), {x:34});
  assert.deepEqual(alignRect(e, 'top',54,85.6), {y:0});
  assert.deepEqual(alignRect(e, 'vcenter',54,85.6), {y:37.8});
  assert.deepEqual(alignRect(e, 'bottom',54,85.6), {y:75.6});
  assert.throws(()=>alignRect(e,'overflow',54,85.6),RangeError);
});

test('stable ordering supports all four layer commands', () => {
  const input=[{id:'back',z:1},{id:'middle',z:3},{id:'front',z:3}];
  assert.deepEqual(reorderLayers(input,'middle','front').map(e=>[e.id,e.z]), [
    ['back',1],['front',2],['middle',3],
  ]);
  assert.deepEqual(reorderLayers(input,'middle','back').map(e=>e.id),['middle','back','front']);
  assert.deepEqual(reorderLayers(input,'middle','up').map(e=>e.id),['back','front','middle']);
  assert.deepEqual(reorderLayers(input,'middle','down').map(e=>e.id),['middle','back','front']);
  assert.equal(input[1].z,3,'original fields must not mutate');
});

test('locked fields never reorder and missing selected ids are ignored', () => {
  const input=[{id:'a',z:1,locked:true},{id:'b',z:2,locked:false}];
  assert.deepEqual(reorderLayers(input,'a','front'),input);
  assert.deepEqual(reorderLayers(input,'none','up'),input);
});

test('smart guides detect card center and neighboring element edges', () => {
  const center=findAlignmentGuides({x:32.7,y:20.4,w:20,h:10},[],85.6,53.98,.65);
  assert.equal(center.vertical,42.8);
  assert.equal(center.x,32.8);
  assert.equal(center.horizontal, null);

  const neighbor=findAlignmentGuides({x:29.6,y:5,w:10,h:7},[
    {x:30,y:20,w:15,h:8},
  ],85.6,53.98,.65);
  assert.equal(neighbor.vertical,30);
  assert.equal(neighbor.x,30);
  assert.equal(neighbor.horizontal,null);
});

test('smart guide tolerance and clamping avoid off-card placement', () => {
  const none=findAlignmentGuides({x:11,y:9,w:10,h:8},[],85.6,53.98,.1);
  assert.equal(none.vertical,null);
  assert.equal(none.horizontal,null);
  assert.deepEqual([none.x,none.y],[11,9]);
  const guide=findAlignmentGuides({x:0.4,y:0.3,w:10,h:8},[],85.6,53.98,.65);
  assert.deepEqual([guide.x,guide.y],[0,0]);
});
