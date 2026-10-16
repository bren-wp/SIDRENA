(function ($) {
	"use strict";

	function bindPoslovnice() {
		var $form = $("#sidrena-poslovnice-form");
		if (!$form.length || typeof sidrenaCijenaAdmin === "undefined") {
			return;
		}

		var i18n = sidrenaCijenaAdmin.i18n || {};
		var $list = $("#sidrena-poslovnice");
		var $json = $("#sidrena-poslovnice-json");
		if (!$list.length || !$json.length) {
			return;
		}
		var woo = !sidrenaCijenaAdmin.standalone;
		var labels = sidrenaCijenaAdmin.productLabels || {};
		var choices = sidrenaCijenaAdmin.catalogChoices || [];

		function textInput(className, value, type) {
			return $("<input>", {
				type: type || "text",
				class: className,
				value: value == null ? "" : value,
			});
		}

		function field(label, $control, helpText, wide) {
			var $wrap = $("<p>", {
				class: "sidrena-poslovnica__field" + (wide ? " sidrena-poslovnica__field--wide" : ""),
			});
			$("<label>").text(label).appendTo($wrap);
			$control.appendTo($wrap);
			if (helpText) {
				$("<span>", { class: "description" }).text(helpText).appendTo($wrap);
			}
			return $wrap;
		}

		function productControl(products) {
			products = $.isArray(products) ? products : [];
			if (woo) {
				var $select = $("<select>", {
					class: "sidrena-poslovnica__select js-products",
					multiple: "multiple",
				});
				products.forEach(function (id) {
					id = String(id);
					$("<option>", {
						value: id,
						text: labels[id] || "#" + id,
						selected: true,
					}).appendTo($select);
				});
				return $select;
			}

			if (!choices.length) {
				return $("<p>", { class: "description js-products-empty" }).text(i18n.noCatalog || "");
			}

			var selected = {};
			products.forEach(function (id) {
				selected[String(id)] = true;
			});
			var $box = $("<div>", { class: "sidrena-poslovnica__checks js-products" });
			choices.forEach(function (choice) {
				var $label = $("<label>");
				$("<input>", {
					type: "checkbox",
					value: String(choice.id),
					checked: !!selected[String(choice.id)],
				}).appendTo($label);
				$label.append(document.createTextNode(" " + (choice.label || "")));
				$box.append($label);
			});
			return $box;
		}

		function initSelect($card) {
			var $select = $card.find("select.js-products");
			if (!$select.length || typeof $.fn.selectWoo !== "function") {
				return;
			}
			$select.selectWoo({
				width: "100%",
				placeholder: i18n.searchProducts || "",
				ajax: {
					url: sidrenaCijenaAdmin.ajaxUrl,
					dataType: "json",
					delay: 250,
					data: function (params) {
						return {
							term: params.term,
							action: "woocommerce_json_search_products_and_variations",
							security: sidrenaCijenaAdmin.productSearchNonce,
						};
					},
					processResults: function (data) {
						var results = [];
						if (data && typeof data === "object") {
							$.each(data, function (id, text) {
								results.push({ id: id, text: text });
							});
						}
						return { results: results };
					},
				},
				minimumInputLength: 2,
			});
		}

		function card(row) {
			row = row || {};
			var $card = $("<article>", { class: "sidrena-poslovnica" });
			$("<input>", { type: "hidden", class: "js-id", value: row.id || "" }).appendTo($card);
			$("<input>", {
				type: "hidden",
				class: "js-products-json",
				value: JSON.stringify($.isArray(row.products) ? row.products : []),
			}).appendTo($card);
			$("<input>", {
				type: "hidden",
				class: "js-stock-json",
				value: JSON.stringify(row.stock && typeof row.stock === "object" ? row.stock : {}),
			}).appendTo($card);
			var $grid = $("<div>", { class: "sidrena-poslovnica__grid" }).appendTo($card);
			field(i18n.outletName, textInput("js-naziv", row.naziv), i18n.outletNameHelp, true).appendTo($grid);
			field(i18n.outletOblik, textInput("js-oblik", row.oblik || "prodavaonica"), i18n.outletOblikHelp).appendTo($grid);
			field(i18n.outletAddress, textInput("js-adresa", row.adresa), i18n.outletAddressHelp).appendTo($grid);
			field(i18n.outletCode, textInput("js-oznaka", row.oznaka || ""), i18n.outletCodeHelp).appendTo($grid);
			field(
				i18n.outletSeq,
				textInput("js-pohrana", row.pohrana || 1, "number").attr({ min: 1, step: 1 }),
				i18n.outletSeqHelp
			).appendTo($grid);

			var $productsWrap = $("<div>", { class: "sidrena-poslovnica__products" });
			$("<label>", { class: "sidrena-poslovnica__products-label" })
				.text(i18n.outletProducts || "Proizvodi")
				.appendTo($productsWrap);
			productControl(row.products).appendTo($productsWrap);
			if (i18n.outletProductsHelp) {
				$("<span>", { class: "description" }).text(i18n.outletProductsHelp).appendTo($productsWrap);
			}
			$productsWrap.appendTo($grid);

			$("<p>", { class: "sidrena-poslovnica__actions" })
				.append(
					$("<button>", {
						type: "button",
						class: "button-link button-link-delete js-remove-outlet",
					})
						.append($("<span>", { class: "dashicons dashicons-trash", "aria-hidden": "true" }))
						.append(document.createTextNode(i18n.removeOutlet || ""))
				)
				.appendTo($card);

			return $card;
		}

		function render(rows) {
			$list.empty();
			rows.forEach(function (row) {
				var $card = card(row);
				$list.append($card);
				initSelect($card);
			});
		}

		function collectProducts($card) {
			var $select = $card.find("select.js-products");
			if ($select.length) {
				var value = $select.val() || [];
				if (!$.isArray(value)) {
					value = [value];
				}
				return value.map(String);
			}

			var ids = [];
			$card.find(".js-products input:checked").each(function () {
				ids.push(String($(this).val()));
			});
			return ids;
		}

		function collect() {
			var rows = [];
			$list.find(".sidrena-poslovnica").each(function () {
				var $card = $(this);
				var products = [];
				var stock = {};
				try {
					products = JSON.parse($card.find(".js-products-json").val() || "[]");
				} catch (e) {
					products = [];
				}
				try {
					stock = JSON.parse($card.find(".js-stock-json").val() || "{}");
				} catch (e2) {
					stock = {};
				}
				products = collectProducts($card);
				$card.find(".js-products-json").val(JSON.stringify(products));
				rows.push({
					id: $card.find(".js-id").val() || "",
					naziv: $card.find(".js-naziv").val() || "",
					oblik: $card.find(".js-oblik").val() || "",
					adresa: $card.find(".js-adresa").val() || "",
					oznaka: $card.find(".js-oznaka").val() || "",
					pohrana: $card.find(".js-pohrana").val() || 1,
					products: products,
					stock: stock && typeof stock === "object" ? stock : {},
				});
			});
			return rows;
		}

		var initial = [];
		try {
			initial = JSON.parse($json.val() || "[]");
		} catch (e) {
			initial = [];
		}
		if (!$.isArray(initial)) {
			initial = [];
		}
		render(initial);

		$("#sidrena-poslovnica-add").on("click", function () {
			var $card = card({ oblik: "prodavaonica", oznaka: "", pohrana: 1 });
			$list.append($card);
			initSelect($card);
			$card.find(".js-naziv").trigger("focus");
		});

		$list.on("click", ".js-remove-outlet", function () {
			var $card = $(this).closest(".sidrena-poslovnica");
			var hasContent = $.trim($card.find(".js-naziv").val() || "") || collectProducts($card).length;
			if (hasContent && !window.confirm(i18n.removeOutletConfirm || "Ukloniti ovu poslovnicu?")) {
				return;
			}
			$card.remove();
			$form.trigger("brendigo:dirty");
		});

		$form.on("submit", function () {
			$json.val(JSON.stringify(collect()));
		});
	}

	$(function () {
		bindPoslovnice();
	});
})(jQuery);
