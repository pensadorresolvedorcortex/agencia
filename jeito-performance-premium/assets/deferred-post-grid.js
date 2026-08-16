(() => {
	'use strict';
	if (!('IntersectionObserver' in window) || typeof bt_bb_css_post_grid !== 'function') return;

	const Grid = bt_bb_css_post_grid;
	const load = Grid.bt_bb_css_post_grid_load_posts.bind(Grid);
	const pending = new WeakMap();

	Grid.bt_bb_css_post_grid_load_posts = (root, offset) => {
		const element = root && root.get ? root.get(0) : null;
		if (offset !== 0 || !element || element.getBoundingClientRect().top < window.innerHeight + 200) {
			load(root, offset);
			return;
		}
		if (pending.has(element)) return;

		const observer = new IntersectionObserver((entries) => {
			if (!entries.some((entry) => entry.isIntersecting)) return;
			observer.disconnect();
			pending.delete(element);
			load(root, 0);
		}, {rootMargin: '200px 0px'});
		pending.set(element, observer);
		observer.observe(element);
	};
})();
