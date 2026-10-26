/**
 * Theme Lab Preview Controller
 *
 * Shared live-preview controller used by every Theme Lab studio.
 *
 * @package ThemeLab
 * @version 1.0.1
 */

(function () {
	'use strict';

	const lineHeights = {
		compact: '1.25',
		comfortable: '1.5',
		relaxed: '1.75'
	};

	const ThemeLabPreview = {
		root: null,
		projectId: 0,

		init() {
			/* Color controls are a global Theme Lab UI standard and do not
			 * depend on the live preview being present. */
			this.bindColorPairs();

			this.root = document.getElementById('themelab_mini_forum');

			if (!this.root) {
				return;
			}

			this.projectId = parseInt(this.root.dataset.projectId || '0', 10) || 0;
			this.bindTypographyControls();
			this.bindPaletteControls();
			this.bindLayoutControls();
			this.bindButtonControls();
			this.bindFormControls();
			this.updateFormFocusDemo();
			this.bindFormFieldSizing();
			this.bindFormPreviewValidation();
			this.bindNavigationControls();
			this.bindBoardIndexControls();
			this.bindHeaderControls();
			this.bindFooterControls();
			this.bindLiveSyncSafetyNet();
			this.syncHeaderPreviewScale();

			/* Keep the header crop representative when the Studio/preview column
			 * changes width. The published header is much wider than this mini
			 * preview, so a literal pixel-for-pixel height makes `cover` appear
			 * artificially zoomed-out in Theme Lab. */
			if (window.ResizeObserver) {
				this.headerPreviewResizeObserver = new ResizeObserver(() => {
					this.syncHeaderPreviewScale();
				});
				this.headerPreviewResizeObserver.observe(this.root);
			}
		},

		/*
		 * Shared helpers.
		 */

		getGradientAngle(direction) {
			return direction === 'vertical'
				? '180deg'
				: '90deg';
		},

		/* Shared color picker and hex field synchronization. */

		normalizeHex(value) {
			let hex = String(value || '').trim();

			if (hex && hex.charAt(0) !== '#') {
				hex = '#' + hex;
			}

			return /^#[0-9a-fA-F]{6}$/.test(hex)
				? hex.toUpperCase()
				: null;
		},

		bindColorPairs() {
			const boundPairs = new Set();

			document
				.querySelectorAll('input[type="color"]')
				.forEach((colorInput) => {
					let hexField = null;

					/* Standard pair: HEX input points to the swatch by id. */
					if (colorInput.id) {
						hexField = document.querySelector(
							'input[type="text"][data-color-target="' +
							colorInput.id +
							'"]'
						);
					}

					/* Gradient Studio legacy pair: swatch points to HEX input. */
					if (!hexField && colorInput.dataset.colorTarget) {
						const target = document.getElementById(
							colorInput.dataset.colorTarget
						);

						if (target && target.matches('input[type="text"]')) {
							hexField = target;
						}
					}

					/* Palette Studio pair: token joins the swatch and HEX input. */
					if (!hexField && colorInput.dataset.token) {
						hexField = document.getElementById(
							'themelab_color_' + colorInput.dataset.token
						);
					}

					if (!hexField) {
						return;
					}

					const pairKey =
						(colorInput.id || colorInput.dataset.colorTarget || '') +
						'::' +
						(hexField.id || colorInput.dataset.token || '');

					if (boundPairs.has(pairKey)) {
						return;
					}

					boundPairs.add(pairKey);
					hexField.classList.add('themelab_color_hex');
					hexField.setAttribute('spellcheck', 'false');
					hexField.setAttribute('autocomplete', 'off');

					let committedValue = colorInput.value.toUpperCase();

					const applyHexValue = () => {
						const hex = this.normalizeHex(hexField.value);

						if (!hex) {
							hexField.classList.add('is_invalid');
							return false;
						}

						hexField.classList.remove('is_invalid');
						hexField.value = hex;
						colorInput.value = hex;
						committedValue = hex;
						colorInput.dispatchEvent(
							new Event('input', { bubbles: true })
						);

						return true;
					};

					colorInput.addEventListener('input', () => {
						committedValue = colorInput.value.toUpperCase();
						hexField.value = committedValue;
						hexField.classList.remove('is_invalid');
					});

					hexField.addEventListener('input', applyHexValue);

					hexField.addEventListener('blur', () => {
						if (!applyHexValue()) {
							hexField.value = committedValue;
							hexField.classList.remove('is_invalid');
						}
					});

					/* Accessibility workflow: one click selects the full HEX value. */
					['focus', 'click'].forEach((eventName) => {
						hexField.addEventListener(eventName, () => {
							hexField.select();
						});
					});

					hexField.addEventListener('keydown', (event) => {
						if (event.key === 'Enter') {
							event.preventDefault();
							if (applyHexValue()) {
								hexField.blur();
							}
						}

						if (event.key === 'Escape') {
							event.preventDefault();
							hexField.value = committedValue;
							hexField.classList.remove('is_invalid');
							hexField.blur();
						}
					});
				});
		},

		/*
		 * Typography preview methods.
		 */

		setFontFamily(fontFamily) {
			if (this.root && fontFamily) {
				this.root.style.fontFamily = fontFamily;
			}
		},

		setFontSize(fontSize) {
			if (!this.root) {
				return;
			}

			const size = parseInt(fontSize, 10);

			if (!Number.isFinite(size)) {
				return;
			}

			const safeSize = Math.min(22, Math.max(10, size));
			this.root.style.fontSize = safeSize + 'px';
		},

		setLineSpacing(spacing) {
			if (this.root && lineHeights[spacing]) {
				this.root.style.lineHeight = lineHeights[spacing];
			}
		},

		setHeadingWeight(weight) {
			if (!this.root) {
				return;
			}

			const safeWeight = ['400', '500', '700'].includes(String(weight))
				? String(weight)
				: '700';

			this.root.dataset.headingWeight = safeWeight;
			this.root.style.setProperty('--themelab-preview-heading-weight', safeWeight);

			this.root
				.querySelectorAll('.themelab_preview_heading, .themelab_preview_form_heading')
				.forEach(function (heading) {
					heading.style.fontWeight = safeWeight;
					heading.dataset.fontWeight = safeWeight;
				});
		},

		setMonospaceFont(fontFamily) {
			if (!this.root || !fontFamily) {
				return;
			}

			/* Keep monospace preview ownership on the shared preview root.
			 * The specimen consumes this variable with !important so no
			 * inherited/code styling can mask the selected family. */
			this.root.style.setProperty('--themelab-preview-monospace', fontFamily);

			this.root
				.querySelectorAll('.themelab_preview_code')
				.forEach(function (codeBlock) {
					codeBlock.style.setProperty('font-family', fontFamily, 'important');
				});
		},

		bindTypographyControls() {
			const fontFamilies =
				window.themeLabTypographyFonts || {};

			const monospaceFamilies =
				window.themeLabMonospaceFonts || {};

			document
				.querySelectorAll(
					'input[name="themelab_font_family"]'
				)
				.forEach((input) => {
					input.addEventListener('change', () => {
						if (fontFamilies[input.value]) {
							this.setFontFamily(
								fontFamilies[input.value]
							);
						}

						document
							.querySelectorAll('.themelab_font_card')
							.forEach(function (card) {
								card.classList.remove('is_selected');
							});

						const card = input.closest(
							'.themelab_font_card'
						);

						if (card) {
							card.classList.add('is_selected');
						}
					});
				});

			const fontSize = document.getElementById(
				'themelab_font_size'
			);

			if (fontSize) {
				fontSize.addEventListener('input', () => {
					this.setFontSize(fontSize.value);
				});
			}

			const lineSpacing = document.getElementById(
				'themelab_line_spacing'
			);

			if (lineSpacing) {
				lineSpacing.addEventListener('change', () => {
					this.setLineSpacing(lineSpacing.value);
				});
			}

			document
				.querySelectorAll(
					'input[name="themelab_heading_weight"]'
				)
				.forEach((input) => {
					input.addEventListener('change', () => {
						this.setHeadingWeight(input.value);
					});
				});

			const monospace = document.getElementById(
				'themelab_monospace_font'
			);

			if (monospace) {
				monospace.addEventListener('change', () => {
					const selected = monospace.options[monospace.selectedIndex];
					const family = selected && selected.dataset.family
						? selected.dataset.family
						: monospaceFamilies[monospace.value];

					if (family) {
						this.setMonospaceFont(family);
					}
				});
			}
		},

		/*
		 * General palette and layout methods.
		 */

		setColor(token, color) {
			if (!this.root || !token || !color) {
				return;
			}

			this.root.style.setProperty(
				'--themelab-preview-' + token.replace(/_/g, '-'),
				color
			);
		},

		bindPaletteControls() {
			const workspace = document.querySelector('.themelab_palette_workspace');
			if (!workspace) return;

			workspace.querySelectorAll('.themelab_color_picker[data-token]').forEach((picker) => {
				const token = picker.getAttribute('data-token');
				const hexField = workspace.querySelector('.themelab_palette_color_hex[data-token="' + token + '"]');

				picker.addEventListener('input', () => {
					const value = picker.value.toUpperCase();
					if (hexField) hexField.value = value;
					this.setColor(token, value);
				});
			});

			workspace.querySelectorAll('.themelab_palette_color_hex[data-token]').forEach((field) => {
				const token = field.getAttribute('data-token');
				const picker = workspace.querySelector('.themelab_color_picker[data-token="' + token + '"]');

				field.addEventListener('input', () => {
					const value = field.value.trim();
					if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;
					const normalized = value.toUpperCase();
					if (picker) picker.value = normalized;
					this.setColor(token, normalized);
				});

				field.addEventListener('blur', () => {
					const value = field.value.trim();
					if (/^#[0-9a-fA-F]{6}$/.test(value)) field.value = value.toUpperCase();
				});
			});
		},

		setLayout(setting, value) {
			if (!this.root) {
				return;
			}

			switch (setting) {
				case 'border_radius': {
					const radius = parseInt(value, 10);

					if (!Number.isFinite(radius)) {
						break;
					}

					const safeRadius = Math.max(0, Math.min(40, radius));

					this.root.style.setProperty(
						'--themelab-preview-radius',
						safeRadius + 'px'
					);
					break;
				}

				case 'max_width': {
					const width = parseInt(value, 10);

					if (!Number.isFinite(width)) {
						break;
					}

					const safeWidth = Math.max(800, Math.min(2400, width));
					const scale = Math.max(
						88,
						Math.min(100, ((safeWidth - 800) / 1600) * 12 + 88)
					);

					this.root.style.setProperty(
						'--themelab-preview-width',
						safeWidth + 'px'
					);
					this.root.style.setProperty(
						'--themelab-preview-width-scale',
						scale + '%'
					);
					break;
				}

				case 'width_mode':
					this.root.dataset.widthMode = value;
					break;

				case 'shadow_style':
					this.root.dataset.shadow = value;
					break;

				case 'content_spacing':
					this.root.dataset.spacing = value;
					break;
			}
		},

		bindLayoutControls() {
			document
				.querySelectorAll('input[name="themelab_width_mode"]')
				.forEach((input) => {
					input.addEventListener('change', () => {
						this.setLayout('width_mode', input.value);
					});
				});

			const maxWidth = document.getElementById(
				'themelab_max_width'
			);

			if (maxWidth) {
				maxWidth.addEventListener('input', () => {
					this.setLayout('max_width', maxWidth.value);
				});
			}

			const borderRadius = document.getElementById(
				'themelab_border_radius'
			);

			if (borderRadius) {
				borderRadius.addEventListener('input', () => {
					this.setLayout(
						'border_radius',
						borderRadius.value
					);
				});
			}

			const shadowStyle = document.getElementById(
				'themelab_shadow_style'
			);

			if (shadowStyle) {
				shadowStyle.addEventListener('change', () => {
					this.setLayout(
						'shadow_style',
						shadowStyle.value
					);
				});
			}

			document
				.querySelectorAll(
					'input[name="themelab_content_spacing"]'
				)
				.forEach((input) => {
					input.addEventListener('change', () => {
						this.setLayout(
							'content_spacing',
							input.value
						);
					});
				});
		},


		/*
		 * Button Studio preview methods.
		 */
		setButtonSetting(setting, value) {
			if (!this.root) return;
			const vars = {
				border_radius: '--themelab-button-radius',
				border_width: '--themelab-button-border-width',
				padding_vertical: '--themelab-button-padding-y',
				padding_horizontal: '--themelab-button-padding-x',
				font_weight: '--themelab-button-font-weight',
				text_transform: '--themelab-button-text-transform',
				primary_text: '--themelab-button-primary-text',
				secondary_text: '--themelab-button-secondary-text',
				danger_background: '--themelab-button-danger-bg',
				danger_text: '--themelab-button-danger-text',
				danger_border: '--themelab-button-danger-border',
				disabled_background: '--themelab-button-disabled-bg',
				disabled_text: '--themelab-button-disabled-text',
				disabled_border: '--themelab-button-disabled-border',
				header_background: '--themelab-header-button-bg',
				header_text: '--themelab-header-button-text',
				header_border: '--themelab-header-button-border',
				header_hover_background: '--themelab-header-button-hover-bg',
				header_hover_text: '--themelab-header-button-hover-text',
				header_current_background: '--themelab-header-button-current-bg',
				header_current_text: '--themelab-header-button-current-text',
				footer_background: '--themelab-footer-button-bg',
				footer_text: '--themelab-footer-button-text',
				footer_border: '--themelab-footer-button-border',
				footer_hover_background: '--themelab-footer-button-hover-bg',
				footer_hover_text: '--themelab-footer-button-hover-text',
				footer_current_background: '--themelab-footer-button-current-bg',
				footer_current_text: '--themelab-footer-button-current-text'
			};

			const numericBounds = {
				border_radius: [0, 40],
				border_width: [0, 5],
				padding_vertical: [3, 24],
				padding_horizontal: [6, 48]
			};

			if (numericBounds[setting]) {
				if (value === '' || value === null) return;
				const number = Number(value);
				if (!Number.isFinite(number)) return;
				const [minimum, maximum] = numericBounds[setting];
				value = Math.min(maximum, Math.max(minimum, number));
			}

			if (vars[setting]) {
				const unit = numericBounds[setting] ? 'px' : '';
				this.root.style.setProperty(vars[setting], value + unit);
			}
			if (setting === 'font_weight') this.root.dataset.buttonWeight = value;
			if (setting === 'depth') this.root.dataset.buttonDepth = value;
			if (setting === 'hover_effect') this.root.dataset.buttonHover = value;
		},

		hexToRgb(hex) {
			const value = String(hex || '').trim().replace(/^#/, '');
			if (!/^[0-9A-Fa-f]{6}$/.test(value)) return null;
			return {
				r: parseInt(value.slice(0, 2), 16),
				g: parseInt(value.slice(2, 4), 16),
				b: parseInt(value.slice(4, 6), 16)
			};
		},

		rgbToHex(rgb) {
			const channel = (value) => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0');
			return ('#' + channel(rgb.r) + channel(rgb.g) + channel(rgb.b)).toUpperCase();
		},

		mixHex(a, b, amount) {
			const left = this.hexToRgb(a);
			const right = this.hexToRgb(b);
			if (!left || !right) return String(a || b || '#3465A4').toUpperCase();
			const t = Math.max(0, Math.min(1, Number(amount) || 0));
			return this.rgbToHex({
				r: left.r + (right.r - left.r) * t,
				g: left.g + (right.g - left.g) * t,
				b: left.b + (right.b - left.b) * t
			});
		},

		colorLuminance(hex) {
			const rgb = this.hexToRgb(hex);
			if (!rgb) return 0.5;
			const convert = (value) => {
				const c = value / 255;
				return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
			};
			return 0.2126 * convert(rgb.r) + 0.7152 * convert(rgb.g) + 0.0722 * convert(rgb.b);
		},

		contrastText(hex) {
			const background = this.colorLuminance(hex);
			const whiteContrast = 1.05 / (background + 0.05);
			const darkContrast = (background + 0.05) / 0.05;
			return whiteContrast >= darkContrast ? '#FFFFFF' : '#111827';
		},

		colorDistance(a, b) {
			const left = this.hexToRgb(a);
			const right = this.hexToRgb(b);
			if (!left || !right) return 0;
			return Math.sqrt(
				Math.pow(left.r - right.r, 2) +
				Math.pow(left.g - right.g, 2) +
				Math.pow(left.b - right.b, 2)
			);
		},

		setButtonSuggestedColor(field, value) {
			const picker = document.getElementById('themelab_button_' + field);
			const hex = document.getElementById('themelab_button_' + field + '_hex');
			if (!picker) return;
			const normalized = String(value || '').toUpperCase();
			picker.value = normalized;
			if (hex) {
				hex.value = normalized;
				hex.removeAttribute('aria-invalid');
			}
			this.setButtonSetting(field, normalized);
			picker.dispatchEvent(new Event('input', { bubbles: true }));
		},

		suggestNavigationButtons(region) {
			if (!this.root || (region !== 'header' && region !== 'footer')) return;

			const styles = getComputedStyle(this.root);
			const read = (name, fallback) => {
				const value = styles.getPropertyValue(name).trim().toUpperCase();
				return /^#[0-9A-F]{6}$/.test(value) ? value : fallback;
			};

			const start = read('--themelab-suggest-' + region + '-a', region === 'header' ? '#3465A4' : '#D5DEE5');
			const end = read('--themelab-suggest-' + region + '-b', start);
			const primary = read('--themelab-suggest-primary', '#3465A4');
			const accent = read('--themelab-suggest-accent', primary);
			const surface = this.mixHex(start, end, 0.5);
			const isLight = this.colorLuminance(surface) > 0.42;

			/* Keep the normal control related to its surface while giving it
			 * enough separation to read as a button. */
			const normal = this.mixHex(surface, isLight ? '#000000' : '#FFFFFF', isLight ? 0.10 : 0.15);
			const normalText = this.contrastText(normal);

			/* Hover is a gentle accent blend, not a random unrelated color. */
			const hover = this.mixHex(normal, accent, 0.22);
			const hoverText = this.contrastText(hover);

			/* Current uses whichever palette color separates most clearly from
			 * the surrounding Header/Footer surface. */
			const currentSeed = this.colorDistance(primary, surface) >= this.colorDistance(accent, surface)
				? primary
				: accent;
			const current = this.mixHex(currentSeed, isLight ? '#000000' : '#FFFFFF', 0.06);
			const currentText = this.contrastText(current);

			/* Border derives from the normal button itself for predictable depth. */
			const border = this.mixHex(normal, normalText, 0.28);

			const values = {};
			values[region + '_background'] = normal;
			values[region + '_text'] = normalText;
			values[region + '_border'] = border;
			values[region + '_hover_background'] = hover;
			values[region + '_hover_text'] = hoverText;
			values[region + '_current_background'] = current;
			values[region + '_current_text'] = currentText;

			Object.keys(values).forEach((field) => this.setButtonSuggestedColor(field, values[field]));
		},

		bindButtonControls() {
			const controls = {
				themelab_button_border_radius: 'border_radius',
				themelab_button_border_width: 'border_width',
				themelab_button_padding_vertical: 'padding_vertical',
				themelab_button_padding_horizontal: 'padding_horizontal',
				themelab_button_font_weight: 'font_weight',
				themelab_button_text_transform: 'text_transform',
				themelab_button_depth: 'depth',
				themelab_button_hover_effect: 'hover_effect',
				themelab_button_primary_text: 'primary_text',
				themelab_button_secondary_text: 'secondary_text',
				themelab_button_danger_background: 'danger_background',
				themelab_button_danger_text: 'danger_text',
				themelab_button_danger_border: 'danger_border',
				themelab_button_disabled_background: 'disabled_background',
				themelab_button_disabled_text: 'disabled_text',
				themelab_button_disabled_border: 'disabled_border',
				themelab_button_header_background: 'header_background',
				themelab_button_header_text: 'header_text',
				themelab_button_header_border: 'header_border',
				themelab_button_header_hover_background: 'header_hover_background',
				themelab_button_header_hover_text: 'header_hover_text',
				themelab_button_header_current_background: 'header_current_background',
				themelab_button_header_current_text: 'header_current_text',
				themelab_button_footer_background: 'footer_background',
				themelab_button_footer_text: 'footer_text',
				themelab_button_footer_border: 'footer_border',
				themelab_button_footer_hover_background: 'footer_hover_background',
				themelab_button_footer_hover_text: 'footer_hover_text',
				themelab_button_footer_current_background: 'footer_current_background',
				themelab_button_footer_current_text: 'footer_current_text'
			};
			Object.keys(controls).forEach((id) => {
				const control = document.getElementById(id);
				if (!control) return;
				const eventName = control.tagName.toLowerCase() === 'select' ? 'change' : 'input';
				control.addEventListener(eventName, () => this.setButtonSetting(controls[id], control.value));
			});

			const suggestHeader = document.getElementById('themelab_suggest_header_buttons');
			if (suggestHeader) suggestHeader.addEventListener('click', () => this.suggestNavigationButtons('header'));

			const suggestFooter = document.getElementById('themelab_suggest_footer_buttons');
			if (suggestFooter) suggestFooter.addEventListener('click', () => this.suggestNavigationButtons('footer'));
		},


		/* Form Controls Studio preview methods. */
		setFormSetting(setting, value) {
			if (!this.root) return;
			const vars = {border_radius:'--themelab-form-radius',border_width:'--themelab-form-border-width',padding_horizontal:'--themelab-form-padding-x',textarea_height:'--themelab-textarea-height',choice_size:'--themelab-choice-size',toggle_size:'--themelab-toggle-size',label_weight:'--themelab-form-label-weight'};
			if (vars[setting]) this.root.style.setProperty(vars[setting], value + (setting === 'label_weight' ? '' : 'px'));
			if (setting === 'textarea_height') this.refreshFormFieldSizing();
			if (setting === 'field_size') this.root.dataset.formSize=value;
			if (setting === 'focus_style') {
				this.root.dataset.formFocus = value;
				this.updateFormFocusDemo();
			}
			if (setting === 'textarea_resize') {
				this.root.dataset.textareaResize = value;

				// Apply the live resize mode directly as well as through the data attribute.
				// This prevents host-theme textarea rules from masking the preview setting.
				this.root.querySelectorAll('.themelab_preview_form textarea').forEach((textarea) => {
					textarea.style.resize = value;
					textarea.style.width = (value === 'horizontal' || value === 'both')
						? 'calc(100% - 14px)'
						: '100%';
				});
			}
			if (setting === 'choice_style') this.root.dataset.choiceStyle=value;
			if (setting === 'toggle_style') this.root.dataset.toggleStyle=value;
			if (setting === 'label_weight') this.root.dataset.formLabelWeight=value;
			if (setting === 'required_marker') this.root.dataset.requiredMarker=value;
			if (setting === 'validation_style') this.root.dataset.validationStyle=value;
		},
		updateFormFocusDemo() {
			if (!this.root) return;

			this.root.querySelectorAll('.themelab_preview_form .themelab_focus_demo')
				.forEach((field) => field.classList.remove('themelab_focus_demo'));

			const sample = this.root.querySelector('.themelab_preview_form input[type="text"]');
			if (sample) sample.classList.add('themelab_focus_demo');
		},

		bindFormControls() {
			['field_size','border_radius','border_width','padding_horizontal','focus_style','textarea_height','textarea_resize','choice_size','choice_style','toggle_size','toggle_style','label_weight','required_marker','validation_style'].forEach((key)=>{
				const el=document.getElementById('themelab_forms_'+key); if(!el) return;
				el.addEventListener(el.tagName.toLowerCase()==='select'?'change':'input',()=>this.setFormSetting(key,el.value));
			});
		},


		bindFormFieldSizing() {
			const textarea = this.root.querySelector('.themelab_preview_form textarea');
			if (!textarea) return;

			this.formTextarea = textarea;
			this.nativeFieldSizing = Boolean(
				window.CSS &&
				typeof window.CSS.supports === 'function' &&
				window.CSS.supports('field-sizing', 'content')
			);

			this.root.classList.toggle('themelab_has_native_field_sizing', this.nativeFieldSizing);
			this.root.classList.toggle('themelab_uses_field_sizing_fallback', !this.nativeFieldSizing);

			if (!this.nativeFieldSizing) {
				textarea.addEventListener('input', () => this.refreshFormFieldSizing());
				textarea.addEventListener('focus', () => this.refreshFormFieldSizing());
			}

			// The Forms preview can begin hidden. Recalculate when the preview mode,
			// width or textarea settings change and the element becomes measurable.
			if (typeof MutationObserver !== 'undefined') {
				this.formFieldSizingObserver = new MutationObserver(() => {
					this.refreshFormFieldSizing();
				});
				this.formFieldSizingObserver.observe(this.root, {
					attributes: true,
					attributeFilter: ['data-preview-mode', 'data-textarea-resize', 'style', 'class']
				});
			}

			if (typeof ResizeObserver !== 'undefined') {
				this.formFieldResizeObserver = new ResizeObserver(() => {
					this.refreshFormFieldSizing();
				});
				this.formFieldResizeObserver.observe(textarea.parentElement || textarea);
			}

			this.refreshFormFieldSizing();
		},

		refreshFormFieldSizing() {
			const textarea = this.formTextarea;
			if (!textarea || this.nativeFieldSizing) return;

			window.requestAnimationFrame(() => {
				if (!textarea.isConnected || textarea.offsetParent === null) return;

				const styles = window.getComputedStyle(textarea);
				const configuredMinimum = parseFloat(
					styles.getPropertyValue('--themelab-textarea-height')
				) || 50;

				textarea.style.height = 'auto';
				textarea.style.height = Math.max(configuredMinimum, textarea.scrollHeight) + 'px';
			});
		},

		bindFormPreviewValidation() {
			const preview = this.root.querySelector('.themelab_preview_form');
			if (!preview) return;

			const submit = preview.querySelector('.themelab_preview_form_submit');
			const email = preview.querySelector('input[type="email"]');
			if (!submit || !email) return;

			submit.addEventListener('click', () => {
				email.reportValidity();
			});
		},


		/* Menu and Navigation Studio preview methods. */
		setNavigationSetting(setting, value) {
			if (!this.root) return;
			const vars = {
				menu_radius: '--themelab-navigation-radius', menu_gap: '--themelab-navigation-gap',
				menu_padding_vertical: '--themelab-navigation-padding-y', menu_padding_horizontal: '--themelab-navigation-padding-x',
				font_weight: '--themelab-navigation-font-weight', text_transform: '--themelab-navigation-text-transform'
			};
			if (vars[setting]) {
				const unit = ['menu_radius','menu_gap','menu_padding_vertical','menu_padding_horizontal'].includes(setting) ? 'px' : '';
				this.root.style.setProperty(vars[setting], value + unit);
			}
			if (setting === 'breadcrumb_style') this.root.dataset.breadcrumbStyle = value;
			if (setting === 'breadcrumb_separator') this.root.dataset.breadcrumbSeparator = value;
			if (setting === 'pagination_style') this.root.dataset.paginationStyle = value;
			if (setting === 'pagination_active_emphasis') this.root.dataset.paginationActive = value;
			if (setting === 'pagination_size') this.root.dataset.paginationSize = value;
			if (setting === 'pagination_active_weight') this.root.dataset.paginationActiveWeight = value;
			if (setting === 'pagination_gap') this.root.style.setProperty('--themelab-pagination-gap', value + 'px');
			if (setting === 'pagination_radius') this.root.style.setProperty('--themelab-pagination-radius', value + 'px');
			if (setting === 'active_emphasis') this.root.dataset.navigationActive = value;
			if (setting === 'footer_style') this.root.dataset.footerNavigationStyle = value;

		},

		bindNavigationControls() {
			const controls = {
				themelab_navigation_menu_radius: 'menu_radius',
				themelab_navigation_menu_gap: 'menu_gap',
				themelab_navigation_menu_padding_vertical: 'menu_padding_vertical',
				themelab_navigation_menu_padding_horizontal: 'menu_padding_horizontal',
				themelab_navigation_font_weight: 'font_weight',
				themelab_navigation_text_transform: 'text_transform',
				themelab_navigation_breadcrumb_style: 'breadcrumb_style',
				themelab_navigation_breadcrumb_separator: 'breadcrumb_separator',
				themelab_navigation_pagination_style: 'pagination_style',
				themelab_navigation_pagination_active_emphasis: 'pagination_active_emphasis',
				themelab_navigation_pagination_size: 'pagination_size',
				themelab_navigation_pagination_gap: 'pagination_gap',
				themelab_navigation_pagination_radius: 'pagination_radius',
				themelab_navigation_pagination_active_weight: 'pagination_active_weight',
				themelab_navigation_active_emphasis: 'active_emphasis',
				themelab_navigation_footer_style: 'footer_style'
			};
			Object.keys(controls).forEach((id) => {
				const control = document.getElementById(id); if (!control) return;
				control.addEventListener(control.tagName.toLowerCase() === 'select' ? 'change' : 'input', () => this.setNavigationSetting(controls[id], control.value));
			});
		},


		/* Board Index Studio preview methods. */
		setBoardIndexSetting(setting, value) {
			if (!this.root) return;
			const vars = {container_radius:'--themelab-board-radius', border_width:'--themelab-board-border-width', panel_padding:'--themelab-board-padding', header_height:'--themelab-board-header-height', header_weight:'--themelab-board-header-weight', header_text:'--themelab-board-header-text', header_link:'--themelab-board-header-link', stats_text:'--themelab-board-stats-text', description_text:'--themelab-board-description-text', unread_indicator:'--themelab-board-unread-indicator', row_background:'--themelab-board-row-background', row_background_alt:'--themelab-board-row-background-alt'};
			if (vars[setting]) this.root.style.setProperty(vars[setting], value + (['header_weight','header_text','header_link','stats_text','description_text','unread_indicator','row_background','row_background_alt'].includes(setting) ? '' : 'px'));
			const data = {shadow_style:'boardShadow',header_alignment:'boardHeaderAlign',header_weight:'boardHeaderWeight',row_spacing:'boardRowSpacing',stats_position:'boardStatsPosition',last_post_layout:'boardLastPost',alternating_rows:'boardAlternating',icon_style:'boardIconStyle'};
			if (data[setting]) this.root.dataset[data[setting]] = value;
		},
		bindBoardIndexControls() {
			['container_radius','border_width','panel_padding','shadow_style','header_height','header_alignment','header_weight','row_spacing','stats_position','last_post_layout','alternating_rows','icon_style','header_text','header_link','stats_text','description_text','unread_indicator','row_background','row_background_alt'].forEach((key)=>{
				const el=document.getElementById('themelab_board_index_'+key); if(!el) return;
				el.addEventListener(el.tagName.toLowerCase()==='select'?'change':'input',()=>this.setBoardIndexSetting(key,el.value));
			});
		},
		/*
		 * Header preview methods.
		 */

		syncHeaderPreviewScale(heightOverride) {
			if (!this.root) return;

			const header = this.root.querySelector('#themelab_preview_header');
			if (!header) return;

			const styles = getComputedStyle(this.root);
			const designWidth = parseFloat(styles.getPropertyValue('--themelab-preview-width')) || 1200;
			const designHeight = Number.isFinite(parseFloat(heightOverride))
				? parseFloat(heightOverride)
				: (parseFloat(styles.getPropertyValue('--themelab-header-height')) || 130);
			const previewWidth = header.getBoundingClientRect().width || this.root.getBoundingClientRect().width;

			if (!previewWidth || !designWidth) return;

			/* Preserve the published header's width:height relationship.
			 * Example: a 1200x200 theme header becomes roughly 470x78 in the
			 * mini preview. `background-size: cover` therefore crops the image
			 * using nearly the same geometry as the generated theme. */
			const scaledHeight = Math.max(1, Math.round(designHeight * (previewWidth / designWidth)));
			header.style.minHeight = scaledHeight + 'px';
			header.dataset.previewScaledHeight = String(scaledHeight);
		},

		setHeaderHeight(value) {
			if (!this.root) {
				return;
			}

			const header = this.root.querySelector(
				'#themelab_preview_header'
			);

			const height = parseInt(value, 10);

			if (header && Number.isFinite(height)) {
				this.root.style.setProperty('--themelab-header-height', height + 'px');
				this.syncHeaderPreviewScale(height);

				const logoSize = document.getElementById('themelab_logo_size');
				this.setLogoSize(logoSize ? logoSize.value : this.root.dataset.logoSize || 100);
			}
		},

		setLogoSize(value) {
			if (!this.root) {
				return;
			}

			const percent = Math.min(100, Math.max(25, parseInt(value, 10) || 100));
			const header = this.root.querySelector('#themelab_preview_header');
			const image = this.root.querySelector('#themelab_preview_logo_image');
			const output = document.getElementById('themelab_logo_size_value');
			const rendered = document.getElementById('themelab_logo_rendered_size');
			const headerHeight = header
				? (parseInt(header.style.minHeight, 10) || parseInt(getComputedStyle(header).minHeight, 10) || 130)
				: 130;

			// Shared preview/publish contract:
			// 100% means at most 82% of header height and 70% of header width.
			const safeHeight = Math.max(1, Math.floor(headerHeight * 0.82));
			const maxHeight = Math.max(1, Math.floor(safeHeight * percent / 100));
			const maxWidthPercent = Math.max(1, Math.floor(70 * percent / 100));

			this.root.dataset.logoSize = String(percent);
			this.root.style.setProperty('--themelab-logo-max-height', maxHeight + 'px');
			this.root.style.setProperty('--themelab-logo-max-width', maxWidthPercent + '%');

			if (image) {
				image.style.maxHeight = maxHeight + 'px';
				image.style.maxWidth = maxWidthPercent + '%';
				image.style.width = 'auto';
				image.style.height = 'auto';
			}

			if (output) {
				output.value = percent + '%';
				output.textContent = percent + '%';
			}

			const updateRenderedSize = () => {
				if (!rendered) return;

				if (!image || image.hidden || !image.getAttribute('src')) {
					rendered.textContent = 'Text logo — image safety limits are inactive.';
					return;
				}

				const box = image.getBoundingClientRect();
				const width = Math.max(0, Math.round(box.width));
				const height = Math.max(0, Math.round(box.height));

				if (width > 0 && height > 0) {
					rendered.textContent = 'Preview size: approximately ' + width + ' × ' + height + 'px · safe height cap ' + maxHeight + 'px';
					return;
				}

				const naturalWidth = image.naturalWidth || parseInt(image.dataset.logoNaturalWidth || '0', 10) || 0;
				const naturalHeight = image.naturalHeight || parseInt(image.dataset.logoNaturalHeight || '0', 10) || 0;

				if (naturalWidth > 0 && naturalHeight > 0) {
					const ratio = naturalWidth / naturalHeight;
					const estimatedHeight = Math.min(maxHeight, naturalHeight);
					const estimatedWidth = Math.round(estimatedHeight * ratio);
					rendered.textContent = 'Safe display cap: about ' + estimatedWidth + ' × ' + Math.round(estimatedHeight) + 'px before width protection.';
				} else {
					rendered.textContent = 'Safe height cap: ' + maxHeight + 'px.';
				}
			};

			if (image && !image.dataset.themeLabSizeBound) {
				image.addEventListener('load', () => {
					this.setLogoSize(document.getElementById('themelab_logo_size')?.value || percent);
				});
				image.dataset.themeLabSizeBound = '1';
			}

			window.requestAnimationFrame(updateRenderedSize);
		},

		setLogoText(value) {
			const text = this.root
				? this.root.querySelector('#themelab_preview_logo_text')
				: null;

			if (text) {
				text.textContent = value;
			}
		},

		setLogoImage(source) {
			if (!this.root) {
				return;
			}

			const logo = this.root.querySelector('#themelab_preview_logo');
			const image = this.root.querySelector('#themelab_preview_logo_image');
			const text = this.root.querySelector('#themelab_preview_logo_text');
			const hasImage = Boolean(source);

			if (logo) {
				logo.setAttribute('data-logo-mode', hasImage ? 'image' : 'text');
			}

			if (image) {
				if (hasImage) {
					image.src = source;
					image.hidden = false;
					window.requestAnimationFrame(() => {
						const logoSize = document.getElementById('themelab_logo_size');
						this.setLogoSize(logoSize ? logoSize.value : this.root.dataset.logoSize || 100);
					});
				} else {
					image.removeAttribute('src');
					image.hidden = true;
				}
			}

			if (text) {
				text.hidden = hasImage;
			}
		},

		setHeaderAttribute(attribute, value) {
			const header = this.root
				? this.root.querySelector('#themelab_preview_header')
				: null;

			if (header) {
				header.setAttribute(
					'data-' + attribute,
					value
				);
			}
		},

		updateHeaderBackground() {
			if (!this.root) return;

			const header = this.root.querySelector('#themelab_preview_header');
			const artwork = this.root.querySelector('#themelab_preview_header_artwork');
			const overlay = this.root.querySelector('#themelab_preview_header_overlay');
			const solid = document.getElementById('themelab_header_background_color');
			const positionX = document.getElementById('themelab_header_background_position_x');
			const positionY = document.getElementById('themelab_header_background_position_y');
			const size = document.getElementById('themelab_header_background_size');
			const imageOpacity = document.getElementById('themelab_header_image_opacity');
			const opacity = document.getElementById('themelab_header_background_opacity');

			if (!header) return;

			const source = header.dataset.backgroundImage || '';
			const hasImage = source !== '';
			const imageAlpha = Math.max(
				0,
				Math.min(100, parseInt(imageOpacity ? imageOpacity.value : header.dataset.imageOpacity || '100', 10) || 0)
			) / 100;
			const overlayAlpha = Math.max(
				0,
				Math.min(100, parseInt(opacity ? opacity.value : header.dataset.overlayOpacity || '35', 10) || 0)
			) / 100;
			const x = Math.max(
				0,
				Math.min(100, parseInt(positionX ? positionX.value : header.dataset.backgroundPositionX || '50', 10) || 0)
			);
			const y = Math.max(
				0,
				Math.min(100, parseInt(positionY ? positionY.value : header.dataset.backgroundPositionY || '50', 10) || 0)
			);
			const imageSize = size ? size.value : (header.dataset.backgroundSize || 'cover');

			// The header owns the saved gradient/solid base. The explicit artwork
			// layer owns the uploaded image, focal point, size and opacity.
			header.style.background = 'var(--themelab-header-base, ' + (solid ? solid.value : '#3465A4') + ')';
			header.dataset.backgroundPositionX = String(x);
			header.dataset.backgroundPositionY = String(y);
			header.dataset.backgroundSize = imageSize;
			header.dataset.imageOpacity = String(Math.round(imageAlpha * 100));
			header.dataset.overlayOpacity = String(Math.round(overlayAlpha * 100));
			header.setAttribute('data-has-background-image', hasImage ? '1' : '0');

			if (artwork) {
				artwork.style.backgroundImage = hasImage
					? 'url("' + source.replace(/"/g, '\\"') + '")'
					: 'none';
				artwork.style.backgroundPosition = x + '% ' + y + '%';
				artwork.style.backgroundSize = imageSize;
				artwork.style.opacity = hasImage ? String(imageAlpha) : '0';
			}

			if (overlay) {
				overlay.style.opacity = hasImage ? String(overlayAlpha) : '0';
			}
		},

		bindHeaderControls() {
			const logoInput = document.getElementById('themelab_logo_image');
			const logoDropzone = document.getElementById('themelab_logo_dropzone');
			const logoBrowse = document.getElementById('themelab_logo_browse');
			const logoRemove = document.getElementById('themelab_remove_logo');
			const logoRemoveLabel = document.getElementById('themelab_logo_remove_label');
			const logoStatus = document.getElementById('themelab_logo_status');
			const logoStatusTitle = document.getElementById('themelab_logo_status_title');
			const logoStatusDetails = document.getElementById('themelab_logo_status_details');
			const logoThumbnail = document.getElementById('themelab_logo_thumbnail');
			const logoSize = document.getElementById('themelab_logo_size');
			const backgroundInput = document.getElementById('themelab_header_background_image');
			const backgroundThumbnail = document.getElementById('themelab_header_background_thumbnail');
			const backgroundStatus = document.getElementById('themelab_header_background_status');
			const backgroundDetails = document.getElementById('themelab_header_background_details');
			const backgroundRemove = document.getElementById('themelab_remove_header_background');
			const backgroundRemoveLabel = document.getElementById('themelab_header_background_remove_label');
			const imageOpacity = document.getElementById('themelab_header_image_opacity');
			const imageOpacityValue = document.getElementById('themelab_header_image_opacity_value');
			const backgroundOpacity = document.getElementById('themelab_header_background_opacity');
			const backgroundOpacityValue = document.getElementById('themelab_header_background_opacity_value');
			const backgroundPosition = document.getElementById('themelab_header_background_position');
			const backgroundPositionX = document.getElementById('themelab_header_background_position_x');
			const backgroundPositionY = document.getElementById('themelab_header_background_position_y');
			const backgroundPositionXValue = document.getElementById('themelab_header_background_position_x_value');
			const backgroundPositionYValue = document.getElementById('themelab_header_background_position_y_value');
			const previewHeader = this.root ? this.root.querySelector('#themelab_preview_header') : null;
			const previewLogoImage = this.root ? this.root.querySelector('#themelab_preview_logo_image') : null;
			const savedBackgroundImage = previewHeader ? (previewHeader.dataset.backgroundImage || '') : '';
			const savedLogoImage = previewLogoImage && previewLogoImage.getAttribute('src')
				? previewLogoImage.getAttribute('src')
				: '';

			const previewLogoFile = (file) => {
				if (!file || !/^image\/(png|jpeg|webp)$/i.test(file.type)) {
					return;
				}

				const reader = new FileReader();
				reader.addEventListener('load', () => {
					this.setLogoImage(reader.result);

					if (logoThumbnail) {
						logoThumbnail.src = reader.result;
						logoThumbnail.hidden = false;
					}

					if (logoStatus) {
						logoStatus.classList.remove('is_empty');
					}

					if (logoStatusTitle) {
						logoStatusTitle.textContent = 'Logo selected';
					}

					if (logoStatusDetails) {
						logoStatusDetails.textContent = file.name + ' — ready to save';
					}

					if (logoDropzone) {
						logoDropzone.classList.add('has_logo');
					}

					if (logoRemove) {
						logoRemove.checked = false;
					}

					if (logoRemoveLabel) {
						logoRemoveLabel.classList.remove('is_hidden');
					}
				});
				reader.readAsDataURL(file);
			};

			if (backgroundInput) {
				backgroundInput.addEventListener('change', () => {
					const file = backgroundInput.files && backgroundInput.files[0];
					if (!file || !/^image\/(png|jpeg|webp)$/i.test(file.type)) return;
					const reader = new FileReader();
					reader.addEventListener('load', () => {
						const header = this.root ? this.root.querySelector('#themelab_preview_header') : null;
						if (header) header.dataset.backgroundImage = reader.result;
						if (backgroundThumbnail) { backgroundThumbnail.src = reader.result; backgroundThumbnail.hidden = false; }
						if (backgroundStatus) backgroundStatus.classList.remove('is_empty');
						if (backgroundDetails) backgroundDetails.textContent = file.name + ' — ready to save';
						if (backgroundRemove) backgroundRemove.checked = false;
						if (backgroundRemoveLabel) backgroundRemoveLabel.classList.remove('is_hidden');
						this.updateHeaderBackground();
					});
					reader.readAsDataURL(file);
				});
			}

			if (backgroundRemove) {
				backgroundRemove.addEventListener('change', () => {
					const header = this.root ? this.root.querySelector('#themelab_preview_header') : null;

					if (backgroundRemove.checked) {
						if (header) header.dataset.backgroundImage = '';
						if (backgroundThumbnail) {
							backgroundThumbnail.hidden = true;
							backgroundThumbnail.removeAttribute('src');
						}
						if (backgroundStatus) backgroundStatus.classList.add('is_empty');
						if (backgroundDetails) backgroundDetails.textContent = 'Background image will be removed after saving.';
					} else if (savedBackgroundImage) {
						if (header) header.dataset.backgroundImage = savedBackgroundImage;
						if (backgroundThumbnail) {
							backgroundThumbnail.src = savedBackgroundImage;
							backgroundThumbnail.hidden = false;
						}
						if (backgroundStatus) backgroundStatus.classList.remove('is_empty');
						if (backgroundDetails) backgroundDetails.textContent = 'Saved background restored.';
					}

					this.updateHeaderBackground();
				});
			}

			if (imageOpacity) {
				const updateImageOpacity = () => { if (imageOpacityValue) imageOpacityValue.textContent = imageOpacity.value + '%'; this.updateHeaderBackground(); };
				imageOpacity.addEventListener('input', updateImageOpacity);
			}

			if (backgroundOpacity) {
				const updateOpacity = () => { if (backgroundOpacityValue) backgroundOpacityValue.textContent = backgroundOpacity.value + '%'; this.updateHeaderBackground(); };
				backgroundOpacity.addEventListener('input', updateOpacity);
			}

			const positionPresets = { center: [50, 50], top: [50, 0], bottom: [50, 100], left: [0, 50], right: [100, 50] };
			if (backgroundPosition) {
				backgroundPosition.addEventListener('change', () => {
					const preset = positionPresets[backgroundPosition.value];
					if (preset) {
						if (backgroundPositionX) backgroundPositionX.value = String(preset[0]);
						if (backgroundPositionY) backgroundPositionY.value = String(preset[1]);
						if (backgroundPositionXValue) backgroundPositionXValue.textContent = preset[0] + '%';
						if (backgroundPositionYValue) backgroundPositionYValue.textContent = preset[1] + '%';
					}
					this.updateHeaderBackground();
				});
			}

			[[backgroundPositionX, backgroundPositionXValue], [backgroundPositionY, backgroundPositionYValue]].forEach(([field, output]) => {
				if (!field) return;

				const updatePosition = () => {
					if (output) output.textContent = field.value + '%';
					if (backgroundPosition) backgroundPosition.value = 'custom';
					this.updateHeaderBackground();
				};

				field.addEventListener('input', updatePosition);
				field.addEventListener('change', updatePosition);
			});

			const backgroundSize = document.getElementById('themelab_header_background_size');
			if (backgroundSize) backgroundSize.addEventListener('change', () => this.updateHeaderBackground());

			if (logoSize) {
				this.setLogoSize(logoSize.value);
				logoSize.addEventListener('input', () => {
					this.setLogoSize(logoSize.value);
				});
			}

			if (logoInput) {
				logoInput.addEventListener('change', () => {
					previewLogoFile(logoInput.files && logoInput.files[0]);
				});
			}

			if (logoBrowse && logoInput) {
				logoBrowse.addEventListener('click', () => logoInput.click());
			}

			if (logoDropzone && logoInput) {
				logoDropzone.addEventListener('click', (event) => {
					if (event.target !== logoInput) {
						logoInput.click();
					}
				});

				logoDropzone.addEventListener('keydown', (event) => {
					if (event.key === 'Enter' || event.key === ' ') {
						event.preventDefault();
						logoInput.click();
					}
				});

				['dragenter', 'dragover'].forEach((name) => {
					logoDropzone.addEventListener(name, (event) => {
						event.preventDefault();
						logoDropzone.classList.add('is_dragging');
					});
				});

				['dragleave', 'drop'].forEach((name) => {
					logoDropzone.addEventListener(name, (event) => {
						event.preventDefault();
						logoDropzone.classList.remove('is_dragging');
					});
				});

				logoDropzone.addEventListener('drop', (event) => {
					const file = event.dataTransfer && event.dataTransfer.files
						? event.dataTransfer.files[0]
						: null;

					if (!file) {
						return;
					}

					try {
						const transfer = new DataTransfer();
						transfer.items.add(file);
						logoInput.files = transfer.files;
					} catch (error) {
						// File preview still works in browsers that block assignment.
					}

					previewLogoFile(file);
				});
			}

			if (logoRemove) {
				logoRemove.addEventListener('change', () => {
					if (logoRemove.checked) {
						if (logoInput) logoInput.value = '';
						this.setLogoImage('');

						if (logoThumbnail) {
							logoThumbnail.hidden = true;
							logoThumbnail.removeAttribute('src');
						}
						if (logoStatus) logoStatus.classList.add('is_empty');
						if (logoStatusTitle) logoStatusTitle.textContent = 'Logo will be removed';
						if (logoStatusDetails) logoStatusDetails.textContent = 'Logo text will be restored after saving.';
					} else if (savedLogoImage) {
						this.setLogoImage(savedLogoImage);

						if (logoThumbnail) {
							logoThumbnail.src = savedLogoImage;
							logoThumbnail.hidden = false;
						}
						if (logoStatus) logoStatus.classList.remove('is_empty');
						if (logoStatusTitle) logoStatusTitle.textContent = 'Saved logo restored';
						if (logoStatusDetails) logoStatusDetails.textContent = 'The saved logo will remain in this workspace.';
					}
				});
			}

			const logoText = document.getElementById(
				'themelab_logo_text'
			);

			if (logoText) {
				logoText.addEventListener('input', () => {
					this.setLogoText(logoText.value);
				});
			}

			const height = document.getElementById(
				'themelab_header_height'
			);

			if (height) {
				height.addEventListener('input', () => {
					this.setHeaderHeight(height.value);
				});
			}

			document
				.querySelectorAll(
					'input[name="themelab_logo_alignment"]'
				)
				.forEach((input) => {
					input.addEventListener('change', () => {
						this.setHeaderAttribute(
							'logo-alignment',
							input.value
						);
					});
				});


			document
				.querySelectorAll('input[name="themelab_smf_logo_visibility"]')
				.forEach((input) => {
					input.addEventListener('change', () => {
						if (input.checked) {
							this.setHeaderAttribute('smf-logo-visibility', input.value);
						}
					});
				});

			document
				.querySelectorAll('input[name="themelab_smf_logo_position"]')
				.forEach((input) => {
					input.addEventListener('change', () => {
						if (input.checked) {
							this.setHeaderAttribute('smf-logo-position', input.value);
						}
					});
				});


			[
				'themelab_header_background_color',
			].forEach((id) => {
				const field = document.getElementById(id);

				if (field) {
					field.addEventListener('input', () => {
						this.updateHeaderBackground();
					});
				}
			});

			// Rebuild the layered background after every page load/save so the saved
			// overlay, focal point and size are applied before the administrator interacts.
			this.updateHeaderBackground();

			const textColor = document.getElementById(
				'themelab_header_text_color'
			);

			if (textColor) {
				textColor.addEventListener('input', () => {
					const header = this.root
						? this.root.querySelector(
							'#themelab_preview_header'
						)
						: null;

					if (header) {
						header.style.color = textColor.value;
					}
				});
			}

			document
				.querySelectorAll(
					'input[name="themelab_navigation_alignment"]'
				)
				.forEach((input) => {
					input.addEventListener('change', () => {
						this.setHeaderAttribute(
							'navigation-alignment',
							input.value
						);
					});
				});

			const navStyle = document.getElementById(
				'themelab_navigation_style'
			);

			if (navStyle) {
				navStyle.addEventListener('change', () => {
					this.setHeaderAttribute(
						'navigation-style',
						navStyle.value
					);
				});
			}

			const searchPosition = document.getElementById(
				'themelab_search_position'
			);

			if (searchPosition) {
				searchPosition.addEventListener('change', () => {
					this.setHeaderAttribute(
						'search-position',
						searchPosition.value
					);
				});
			}
		},

		/*
		 * Footer preview methods.
		 */

		setFooterHeight(value) {
			const footer = this.root
				? this.root.querySelector('#themelab_preview_footer')
				: null;

			const height = parseInt(value, 10);

			if (footer && Number.isFinite(height)) {
				footer.style.minHeight = height + 'px';
			}
		},

		setFooterText(value) {
			const footerText = this.root
				? this.root.querySelector(
					'#themelab_preview_footer_text'
				)
				: null;

			if (footerText) {
				// Decode HTML character entities for preview, but assign the
				// result with textContent so administrator text can never become
				// executable markup.
				const decoder = document.createElement('textarea');
				decoder.innerHTML = String(value || '');
				footerText.textContent = decoder.value;
			}
		},

		setFooterAttribute(attribute, value) {
			const footer = this.root
				? this.root.querySelector('#themelab_preview_footer')
				: null;

			if (footer) {
				footer.setAttribute(
					'data-' + attribute,
					value
				);
			}
		},

		updateFooterBackground() {
			if (!this.root) {
				return;
			}

			const footer = this.root.querySelector(
				'#themelab_preview_footer'
			);
			const solid = document.getElementById(
				'themelab_footer_background_color'
			);

			if (footer && solid) {
				footer.style.background = solid.value;
			}
		},

		bindFooterControls() {
			const footerText = document.getElementById(
				'themelab_footer_text'
			);

			if (footerText) {
				footerText.addEventListener('input', () => {
					this.setFooterText(footerText.value);
				});
			}

			const footerHeight = document.getElementById(
				'themelab_footer_height'
			);

			if (footerHeight) {
				footerHeight.addEventListener('input', () => {
					this.setFooterHeight(footerHeight.value);
				});
			}

			document
				.querySelectorAll(
					'input[name="themelab_footer_text_alignment"]'
				)
				.forEach((input) => {
					input.addEventListener('change', () => {
						this.setFooterAttribute(
							'text-alignment',
							input.value
						);
					});
				});


			['themelab_footer_background_color'].forEach((id) => {
				const field = document.getElementById(id);

				if (field) {
					field.addEventListener('input', () => {
						this.updateFooterBackground();
					});
				}
			});


			const textColor = document.getElementById(
				'themelab_footer_text_color'
			);

			if (textColor) {
				textColor.addEventListener('input', () => {
					const footer = this.root
						? this.root.querySelector(
							'#themelab_preview_footer'
						)
						: null;

					if (footer) {
						footer.style.color = textColor.value;
					}
				});
			}

			const linkColor = document.getElementById(
				'themelab_footer_link_color'
			);

			if (linkColor) {
				linkColor.addEventListener('input', () => {
					const footer = this.root
						? this.root.querySelector(
							'#themelab_preview_footer'
						)
						: null;

					if (footer) {
						footer.style.setProperty(
							'--themelab-preview-footer-link',
							linkColor.value
						);
					}
				});
			}

			const showCredit = document.getElementById(
				'themelab_footer_show_credit'
			);

			if (showCredit) {
				showCredit.addEventListener('change', () => {
					const credit = this.root
						? this.root.querySelector(
							'#themelab_preview_footer_credit'
						)
						: null;

					if (credit) {
						credit.classList.toggle(
							'is_hidden',
							!showCredit.checked
						);
					}
				});
			}
		},

		/*
		 * dev14 preview live-sync safety net.
		 *
		 * Studio-specific binders above remain the primary path. This delegated
		 * fallback guarantees that dynamically rendered/replaced controls still
		 * update the shared preview and keeps every Studio on one preview engine.
		 */
		bindLiveSyncSafetyNet() {
			if (this.liveSyncSafetyNetBound) return;
			this.liveSyncSafetyNetBound = true;

			const buttonMap = {
				themelab_button_border_radius:'border_radius',
				themelab_button_border_width:'border_width',
				themelab_button_padding_vertical:'padding_vertical',
				themelab_button_padding_horizontal:'padding_horizontal',
				themelab_button_font_weight:'font_weight',
				themelab_button_text_transform:'text_transform',
				themelab_button_depth:'depth',
				themelab_button_hover_effect:'hover_effect',
				themelab_button_primary_text:'primary_text',
				themelab_button_secondary_text:'secondary_text',
				themelab_button_danger_background:'danger_background',
				themelab_button_danger_text:'danger_text',
				themelab_button_danger_border:'danger_border',
				themelab_button_disabled_background:'disabled_background',
				themelab_button_disabled_text:'disabled_text',
				themelab_button_disabled_border:'disabled_border',
				themelab_button_header_background:'header_background',
				themelab_button_header_text:'header_text',
				themelab_button_header_border:'header_border',
				themelab_button_header_hover_background:'header_hover_background',
				themelab_button_header_hover_text:'header_hover_text',
				themelab_button_header_current_background:'header_current_background',
				themelab_button_header_current_text:'header_current_text',
				themelab_button_footer_background:'footer_background',
				themelab_button_footer_text:'footer_text',
				themelab_button_footer_border:'footer_border',
				themelab_button_footer_hover_background:'footer_hover_background',
				themelab_button_footer_hover_text:'footer_hover_text',
				themelab_button_footer_current_background:'footer_current_background',
				themelab_button_footer_current_text:'footer_current_text'
			};

			const boardPrefix = 'themelab_board_index_';
			const boardKeys = new Set([
				'container_radius','border_width','panel_padding','shadow_style',
				'header_height','header_alignment','header_weight','row_spacing',
				'stats_position','last_post_layout','alternating_rows','icon_style',
				'header_text','header_link','stats_text','description_text',
				'unread_indicator','row_background','row_background_alt'
			]);

			const navigationPrefix = 'themelab_navigation_';
			const navigationKeys = new Set([
				'menu_radius','menu_gap','menu_padding_vertical','menu_padding_horizontal',
				'font_weight','text_transform','breadcrumb_style','breadcrumb_separator',
				'pagination_style','pagination_active_emphasis','pagination_size',
				'pagination_gap','pagination_radius','pagination_active_weight',
				'active_emphasis','footer_style'
			]);

			const sync = (control) => {
				if (!control || !this.root) return;

				/* Palette colors. */
				const token = control.dataset ? control.dataset.token : '';
				if (token && (control.matches('.themelab_color_picker') || control.matches('.themelab_palette_color_hex'))) {
					const color = this.normalizeHex(control.value);
					if (color) this.setColor(token, color);
					return;
				}

				/* Buttons Studio. */
				if (buttonMap[control.id]) {
					this.setButtonSetting(buttonMap[control.id], control.value);
					return;
				}

				/* Board Index Studio. */
				if (control.id && control.id.indexOf(boardPrefix) === 0) {
					const key = control.id.slice(boardPrefix.length);
					if (boardKeys.has(key)) this.setBoardIndexSetting(key, control.value);
					return;
				}

				/* Navigation Studio. */
				if (control.id && control.id.indexOf(navigationPrefix) === 0) {
					const key = control.id.slice(navigationPrefix.length);
					if (navigationKeys.has(key)) this.setNavigationSetting(key, control.value);
					return;
				}

				/* Layout Studio. */
				if (control.id === 'themelab_max_width') this.setLayout('max_width', control.value);
				else if (control.id === 'themelab_border_radius') this.setLayout('border_radius', control.value);
				else if (control.id === 'themelab_shadow_style') this.setLayout('shadow_style', control.value);
				else if (control.name === 'themelab_width_mode' && control.checked) this.setLayout('width_mode', control.value);
				else if (control.name === 'themelab_content_spacing' && control.checked) this.setLayout('content_spacing', control.value);

				/* Typography Studio. */
				else if (control.id === 'themelab_font_size') this.setFontSize(control.value);
				else if (control.id === 'themelab_line_spacing') this.setLineSpacing(control.value);
				else if (control.name === 'themelab_heading_weight' && control.checked) this.setHeadingWeight(control.value);
				else if (control.id === 'themelab_monospace_font') {
					const option = control.options && control.options[control.selectedIndex];
					if (option && option.dataset.family) this.setMonospaceFont(option.dataset.family);
				}
				else if (control.name === 'themelab_font_family' && control.checked) {
					const card = control.closest('[style*="font-family"]');
					if (card) this.setFontFamily(card.style.fontFamily);
				}

				/* Header/Footer direct-color and geometry controls. */
				else if (control.id === 'themelab_header_height') this.setHeaderHeight(control.value);
				else if (control.id === 'themelab_logo_text') this.setLogoText(control.value);
				else if (control.id === 'themelab_logo_size') this.setLogoSize(control.value);
				else if (control.id === 'themelab_header_background_color') this.updateHeaderBackground();
				else if (control.id === 'themelab_header_text_color') {
					const header = this.root.querySelector('#themelab_preview_header');
					if (header) header.style.color = control.value;
				}
				else if (control.id === 'themelab_footer_height') this.setFooterHeight(control.value);
				else if (control.id === 'themelab_footer_text') this.setFooterText(control.value);
				else if (control.id === 'themelab_footer_background_color') this.updateFooterBackground();
				else if (control.id === 'themelab_footer_text_color') {
					const footer = this.root.querySelector('#themelab_preview_footer');
					if (footer) footer.style.color = control.value;
				}
				else if (control.id === 'themelab_footer_link_color') {
					const footer = this.root.querySelector('#themelab_preview_footer');
					if (footer) footer.style.setProperty('--themelab-preview-footer-link', control.value);
				}
			};

			document.addEventListener('input', (event) => sync(event.target), true);
			document.addEventListener('change', (event) => sync(event.target), true);
		},

	};

	window.ThemeLabPreview = ThemeLabPreview;

	document.addEventListener('DOMContentLoaded', function () {
		ThemeLabPreview.init();
	});
})();
/* RC6.1.21dl - Gradient Studio authority cleanup. */
(function () {
	'use strict';

	const surfaces = {
		header: '#themelab_preview_header',
		footer: '#themelab_preview_footer',
		category_header: '.themelab_preview_board_header, .themelab_preview_category_header',
		card_header: '.themelab_preview_topic_header'
	};

	const linearFlows = [
		['left_right', 'Left to Right'],
		['right_left', 'Right to Left'],
		['top_bottom', 'Top to Bottom'],
		['bottom_top', 'Bottom to Top']
	];

	const centerFlows = [
		['left_right', 'Vertical Highlight'],
		['top_bottom', 'Horizontal Highlight']
	];

	function isHex(value) {
		return /^#[0-9A-F]{6}$/.test(String(value || '').toUpperCase());
	}

	function buildGradient(start, end, type, flow, softness) {
		softness = Math.max(10, Math.min(90, parseInt(softness, 10) || 50));
		const low = 50 - softness / 2;
		const high = 50 + softness / 2;
		const angles = {
			left_right: '90deg',
			right_left: '270deg',
			top_bottom: '180deg',
			bottom_top: '0deg'
		};

		if (type === 'center') {
			flow = flow === 'top_bottom' || flow === 'bottom_top' ? 'top_bottom' : 'left_right';
		}

		const angle = angles[flow] || angles.left_right;

		if (type === 'center') {
			return 'linear-gradient(' + angle + ', ' + end + ' 0%, ' + end + ' ' + low + '%, ' + start + ' 50%, ' + end + ' ' + high + '%, ' + end + ' 100%)';
		}

		return 'linear-gradient(' + angle + ', ' + start + ' 0%, ' + start + ' ' + low + '%, ' + end + ' ' + high + '%, ' + end + ' 100%)';
	}

	function updateFlowHelp(prefix, type, flow) {
		const help = document.getElementById(prefix + '_flow_help');
		if (!help) return;

		if (type === 'center') {
			help.textContent = flow === 'top_bottom'
				? 'Horizontal highlight through the middle, fading toward the top and bottom.'
				: 'Vertical highlight through the middle, fading toward the left and right.';
		} else {
			help.textContent = 'Flow controls the direction from the start color toward the end color.';
		}
	}

	function rebuildFlowOptions(prefix) {
		const type = document.getElementById(prefix + '_type');
		const flow = document.getElementById(prefix + '_flow');
		if (!type || !flow) return;

		const previous = flow.value;
		if (type.value === 'center' && linearFlows.some(item => item[0] === previous)) {
			flow.dataset.linearFlow = previous;
		}

		const options = type.value === 'center' ? centerFlows : linearFlows;
		let desired;

		if (type.value === 'center') {
			desired = previous === 'top_bottom' || previous === 'bottom_top' ? 'top_bottom' : 'left_right';
		} else {
			desired = flow.dataset.linearFlow || previous || 'left_right';
			if (!linearFlows.some(item => item[0] === desired)) desired = 'left_right';
		}

		flow.replaceChildren();
		options.forEach(function (item) {
			const option = document.createElement('option');
			option.value = item[0];
			option.textContent = item[1];
			option.selected = item[0] === desired;
			flow.appendChild(option);
		});
	}

	function updateSurface(surface) {
		const prefix = 'themelab_gradient_' + surface;
		const enabled = document.getElementById(prefix + '_enabled');
		const start = document.getElementById(prefix + '_start');
		const end = document.getElementById(prefix + '_end');
		const type = document.getElementById(prefix + '_type');
		const flow = document.getElementById(prefix + '_flow');
		const softness = document.getElementById(prefix + '_softness');
		const value = document.getElementById(prefix + '_softness_value');
		const sample = document.getElementById(prefix + '_sample');
		const state = document.getElementById(prefix + '_state');
		const section = enabled ? enabled.closest('.themelab_gradient_surface') : null;
		const root = document.getElementById('themelab_mini_forum');

		if (!start || !end || !type || !flow || !softness || !root) return;
		if (!isHex(start.value) || !isHex(end.value)) return;

		const css = buildGradient(
			start.value.toUpperCase(),
			end.value.toUpperCase(),
			type.value,
			flow.value,
			softness.value
		);
		const isEnabled = !!(enabled && enabled.checked);

		if (value) value.textContent = softness.value + '%';
		if (sample) {
			sample.style.background = css;
			sample.classList.toggle('is_disabled', !isEnabled);
		}
		if (state) {
			state.textContent = isEnabled ? 'Gradient active' : 'Gradient currently off — settings are saved but not applied.';
			state.classList.toggle('is_inactive', !isEnabled);
		}
		if (section) section.classList.toggle('is_gradient_inactive', !isEnabled);

		updateFlowHelp(prefix, type.value, flow.value);

		switch (surface) {
			case 'header': {
				const header = root.querySelector('#themelab_preview_header');
				root.style.setProperty('--themelab-header-base', isEnabled ? css : 'var(--themelab-header-solid, #3465A4)');
				if (window.ThemeLabPreview && typeof window.ThemeLabPreview.updateHeaderBackground === 'function') {
					window.ThemeLabPreview.updateHeaderBackground();
				} else if (header) {
					header.style.background = 'var(--themelab-header-base, var(--themelab-header-solid, #3465A4))';
				}
				break;
			}
			case 'footer': {
				const footer = root.querySelector('#themelab_preview_footer');
				if (footer) {
					footer.style.background = isEnabled
						? css
						: 'var(--themelab-footer-solid, var(--themelab-preview-footer, #D5DEE5))';
				}
				break;
			}
			case 'category_header':
				root.style.setProperty(
					'--themelab-category-header-background',
					isEnabled ? css : 'var(--themelab-category-header-solid, var(--themelab-preview-primary))'
				);
				break;
			case 'card_header':
				root.style.setProperty(
					'--themelab-card-header-background',
					isEnabled ? css : 'var(--themelab-card-header-solid, var(--themelab-preview-secondary, var(--themelab-preview-primary)))'
				);
				break;
		}
	}

	function bindColorPair(surface, prefix, fieldName) {
		const text = document.getElementById(prefix + '_' + fieldName);
		const picker = document.querySelector(
			'input[type="color"][data-color-target="' + prefix + '_' + fieldName + '"]'
		);
		if (!text) return;

		text.addEventListener('input', function () {
			const normalized = text.value.toUpperCase();
			if (!isHex(normalized)) {
				text.setAttribute('aria-invalid', 'true');
				return;
			}

			text.value = normalized;
			text.removeAttribute('aria-invalid');
			if (picker) picker.value = normalized;
			updateSurface(surface);
		});

		text.addEventListener('change', function () {
			const normalized = text.value.toUpperCase();
			if (isHex(normalized)) {
				text.value = normalized;
				text.removeAttribute('aria-invalid');
				if (picker) picker.value = normalized;
				updateSurface(surface);
			}
		});

		if (picker) {
			picker.addEventListener('input', function () {
				text.value = picker.value.toUpperCase();
				text.removeAttribute('aria-invalid');
				updateSurface(surface);
			});
		}
	}

	function bind() {
		Object.keys(surfaces).forEach(function (surface) {
			const prefix = 'themelab_gradient_' + surface;
			const enabled = document.getElementById(prefix + '_enabled');
			const type = document.getElementById(prefix + '_type');
			const flow = document.getElementById(prefix + '_flow');
			const softness = document.getElementById(prefix + '_softness');

			bindColorPair(surface, prefix, 'start');
			bindColorPair(surface, prefix, 'end');

			if (enabled) {
				enabled.addEventListener('change', function () {
					updateSurface(surface);
				});
			}

			if (type) {
				type.addEventListener('change', function () {
					rebuildFlowOptions(prefix);
					updateSurface(surface);
				});
			}

			if (flow) {
				flow.addEventListener('change', function () {
					if (type && type.value === 'linear') flow.dataset.linearFlow = flow.value;
					updateSurface(surface);
				});
			}

			if (softness) {
				softness.addEventListener('input', function () {
					updateSurface(surface);
				});
				softness.addEventListener('change', function () {
					updateSurface(surface);
				});
			}

			rebuildFlowOptions(prefix);
			updateSurface(surface);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();


/* RC6.1.21dl: Header utility integration preview + logo guardrail. */
(function () {
	'use strict';
	function initHeaderUtilities() {
		const root = document.getElementById('themelab_mini_forum');
		const header = root ? root.querySelector('#themelab_preview_header') : null;
		const lane = root ? root.querySelector('#themelab_preview_utility_lane') : null;
		const search = root ? root.querySelector('#themelab_preview_search') : null;
		const tools = root ? root.querySelector('.themelab_preview_header_tools') : null;
		if (!header || !lane) return;

		const modeInputs = Array.from(document.querySelectorAll('input[name="themelab_utility_mode"]'));
		const posInputs = Array.from(document.querySelectorAll('input[name="themelab_utility_position"]'));
		const logoInputs = Array.from(document.querySelectorAll('input[name="themelab_logo_alignment"]'));

		function selected(inputs, fallback) {
			const hit = inputs.find(el => el.checked);
			return hit ? hit.value : fallback;
		}
		function resolvePosition(requested, logo) {
			if (requested === 'auto') return logo === 'right' ? 'left' : 'right';
			if (logo === 'left' && requested === 'left') return 'right';
			if (logo === 'right' && requested === 'right') return 'left';
			return requested;
		}
		function update() {
			const mode = selected(modeInputs, header.dataset.utilityMode || 'separate');
			const requested = selected(posInputs, header.dataset.utilityPosition || 'auto');
			const logo = selected(logoInputs, header.dataset.logoAlignment || 'left');
			const resolved = resolvePosition(requested, logo);
			header.dataset.utilityMode = mode;
			header.dataset.utilityPosition = requested;
			header.dataset.utilityResolved = resolved;

			posInputs.forEach(el => {
				const conflict = (logo === 'left' && el.value === 'left') || (logo === 'right' && el.value === 'right');
				el.disabled = conflict;
				const label = el.closest('label');
				if (label) label.classList.toggle('is_disabled', conflict);
			});
			if (search && tools) {
				if (mode === 'integrated') lane.appendChild(search);
				else tools.insertBefore(search, tools.firstChild);
			}
		}
		[...modeInputs, ...posInputs, ...logoInputs].forEach(el => el.addEventListener('change', update));
		update();
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initHeaderUtilities);
	else initHeaderUtilities();
})();
