(function() {
	"use strict";

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var PanelBody = wp.components.PanelBody;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var __ = wp.i18n.__;

	var SSR = null;
	if ( typeof wp.serverSideRender !== "undefined" ) {
		SSR = wp.serverSideRender.default || wp.serverSideRender;
	} else if ( wp.editor && wp.editor.ServerSideRender ) {
		SSR = wp.editor.ServerSideRender;
	}

	var blockData = window.ccfBlockData || {};
	var forms = blockData.forms || [];

	var selectOptions = [{ value: 0, label: blockData.selectText || "— Select a form —" }];
	forms.forEach(function(f) {
		selectOptions.push({ value: f.value, label: f.label });
	});

	var themeOptions = [
		{ value: "", label: "Default" },
		{ value: "light", label: "Light" },
		{ value: "dark", label: "Dark" }
	];

	wp.blocks.registerBlockType("ccf/form-block", {
		title: blockData.blockTitle || "Contact Form (CCF)",
		description: blockData.blockDesc || "Display an existing Custom Contact Form.",
		icon: "feedback",
		category: "widgets",
		keywords: ["form", "contact", "ccf"],
		attributes: {
			formId: { type: "number", default: 0 },
			showTitle: { type: "boolean", default: true },
			showDescription: { type: "boolean", default: true },
			theme: { type: "string", default: "" }
		},
		supports: { html: false },

		edit: function(props) {
			var formId = props.attributes.formId;
			var showTitle = props.attributes.showTitle;
			var showDescription = props.attributes.showDescription;
			var theme = props.attributes.theme;

			function onChangeForm(val) {
				props.setAttributes({ formId: parseInt(val, 10) || 0 });
			}

			if (forms.length === 0) {
				return el(Placeholder, { icon: "feedback", label: blockData.blockTitle || "Contact Form (CCF)" },
					el("p", null, blockData.noFormsText || "No forms found."),
					el("a", { href: blockData.adminUrl || "#", className: "components-button is-primary" }, __("Create a Form", "custom-contact-forms"))
				);
			}

			if (!formId) {
				return el(Fragment, null,
					el(InspectorControls, null,
						el(PanelBody, { title: blockData.blockTitle },
							el(SelectControl, { label: blockData.selectText, value: formId, options: selectOptions, onChange: onChangeForm })
						)
					),
					el(Placeholder, { icon: "feedback", label: blockData.blockTitle || "Contact Form (CCF)" },
						el(SelectControl, { label: blockData.selectText, value: formId, options: selectOptions, onChange: onChangeForm })
					)
				);
			}

			var preview;
			if (SSR) {
				preview = el(SSR, { block: "ccf/form-block", attributes: props.attributes });
			} else {
				var name = "";
				forms.forEach(function(f) { if (f.value === formId) name = f.label; });
				preview = el("div", { style: { padding: "20px", background: "#f0f0f0", border: "1px dashed #ccc", textAlign: "center" } },
					el("span", { className: "dashicons dashicons-feedback", style: { fontSize: "24px", display: "block", marginBottom: "8px" } }),
					el("strong", null, name || "#" + formId),
					el("p", { style: { color: "#666", margin: "8px 0 0" } }, __("Form renders on the front end.", "custom-contact-forms"))
				);
			}

			var editUrl = (blockData.editFormUrl || "#") + formId;

			return el(Fragment, null,
				el(InspectorControls, null,
					el(PanelBody, { title: blockData.blockTitle, initialOpen: true },
						el(SelectControl, {
							label: blockData.selectText,
							value: formId,
							options: selectOptions,
							onChange: onChangeForm
						}),
						el("a", {
							href: editUrl,
							className: "components-button is-secondary",
							style: { marginBottom: "16px", display: "inline-block" },
							target: "_blank",
							rel: "noopener noreferrer"
						}, blockData.editFormText || "Edit Form")
					),
					el(PanelBody, { title: blockData.settingsLabel || "Form Settings", initialOpen: true },
						el(ToggleControl, {
							label: blockData.showTitleLabel || "Show Title",
							checked: showTitle,
							onChange: function(val) { props.setAttributes({ showTitle: val }); }
						}),
						el(ToggleControl, {
							label: blockData.showDescLabel || "Show Description",
							checked: showDescription,
							onChange: function(val) { props.setAttributes({ showDescription: val }); }
						}),
						el(SelectControl, {
							label: blockData.themeLabel || "Theme",
							value: theme,
							options: themeOptions,
							onChange: function(val) { props.setAttributes({ theme: val }); }
						})
					)
				),
				el("div", { className: "ccf-gutenberg-block-preview" }, preview)
			);
		},

		save: function() { return null; }
	});
})();
