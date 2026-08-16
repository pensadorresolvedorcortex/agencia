'use strict';
const assert = require('node:assert');
let observer;
global.window = {innerHeight: 800};
global.IntersectionObserver = class {
	constructor(callback, options) { this.callback = callback; this.options = options; observer = this; }
	observe(element) { this.element = element; }
	disconnect() { this.disconnected = true; }
};
window.IntersectionObserver = global.IntersectionObserver;
let loads = 0;
global.bt_bb_css_post_grid = class {
	static bt_bb_css_post_grid_load_posts() { loads += 1; }
};
require('../jeito-performance-premium/assets/deferred-post-grid.js');

const distant = {getBoundingClientRect: () => ({top: 1400})};
const distantRoot = {get: () => distant};
bt_bb_css_post_grid.bt_bb_css_post_grid_load_posts(distantRoot, 0);
assert.equal(loads, 0);
assert.equal(observer.options.rootMargin, '200px 0px');
observer.callback([{isIntersecting: true}]);
assert.equal(loads, 1);
assert.equal(observer.disconnected, true);

const nearRoot = {get: () => ({})};
bt_bb_css_post_grid.bt_bb_css_post_grid_load_posts(nearRoot, 0);
assert.equal(loads, 1);
observer.callback([{isIntersecting: true}]);
assert.equal(loads, 2);
bt_bb_css_post_grid.bt_bb_css_post_grid_load_posts(distantRoot, 6);
assert.equal(loads, 3);
console.log('Deferred grid behavior tests passed.');
