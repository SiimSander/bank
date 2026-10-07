const INFO_RING_TOOLTIP_GAP = 8;

const hideInfoRingTooltip = (ring) => {
	const tooltip = ring.querySelector('.info-ring__tooltip');

	if (!tooltip) {
		return;
	}

	tooltip.classList.remove('info-ring__tooltip--visible', 'info-ring__tooltip--caret-right');
	tooltip.removeAttribute('style');
};

const positionInfoRingTooltip = (ring) => {
	const trigger = ring.querySelector('.info-ring__trigger');
	const tooltip = ring.querySelector('.info-ring__tooltip');

	if (!trigger || !tooltip) {
		return;
	}

	tooltip.classList.add('info-ring__tooltip--visible');
	tooltip.style.visibility = 'hidden';
	tooltip.style.display = 'block';
	tooltip.style.left = '0';
	tooltip.style.top = '0';

	const triggerRect = trigger.getBoundingClientRect();
	const tooltipRect = tooltip.getBoundingClientRect();
	const viewportPadding = 8;

	let caretOnRight = false;
	let left = triggerRect.right + INFO_RING_TOOLTIP_GAP;

	if (left + tooltipRect.width > window.innerWidth - viewportPadding) {
		left = triggerRect.left - tooltipRect.width - INFO_RING_TOOLTIP_GAP;
		caretOnRight = true;
	}

	let top = triggerRect.top + (triggerRect.height - tooltipRect.height) / 2;
	top = Math.max(
		viewportPadding,
		Math.min(top, window.innerHeight - tooltipRect.height - viewportPadding),
	);

	tooltip.classList.toggle('info-ring__tooltip--caret-right', caretOnRight);
	tooltip.style.left = `${left}px`;
	tooltip.style.top = `${top}px`;
	tooltip.style.visibility = 'visible';
};

const initInfoRings = () => {
	document.querySelectorAll('.info-ring').forEach(($ring) => {
		const trigger = $ring.querySelector('.info-ring__trigger');

		if (!trigger) {
			return;
		}

		let hideTimeout = null;

		const show = () => {
			clearTimeout(hideTimeout);
			positionInfoRingTooltip($ring);
		};

		const scheduleHide = () => {
			hideTimeout = setTimeout(() => {
				hideInfoRingTooltip($ring);
			}, 80);
		};

		trigger.addEventListener('mouseenter', show);
		trigger.addEventListener('focus', show);
		trigger.addEventListener('mouseleave', scheduleHide);
		trigger.addEventListener('blur', scheduleHide);
	});
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initInfoRings);
} else {
	initInfoRings();
}
