(function () {
	var root = document.documentElement;
	try { if (localStorage.getItem('corim-dark-mode') === '1') { root.classList.add('dark'); } } catch (e) {}
	function updateLabel() {
		var toggles = document.querySelectorAll('.theme-toggle');
		var text = root.classList.contains('dark') ? 'Mode clair' : 'Mode sombre';
		for (var i = 0; i < toggles.length; i++) { toggles[i].title = text; toggles[i].setAttribute('aria-label', text); }
	}
	document.addEventListener('DOMContentLoaded', function () {
		updateLabel();
		document.addEventListener('click', function (e) {
			var toggle = e.target.closest ? e.target.closest('.theme-toggle') : null;
			if (!toggle) { return; }
			e.preventDefault();
			var active = root.classList.toggle('dark');
			try { localStorage.setItem('corim-dark-mode', active ? '1' : '0'); } catch (err) {}
			updateLabel();
		});
	});
})();
