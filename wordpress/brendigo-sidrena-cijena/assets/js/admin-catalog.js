(function ($) {
	"use strict";

	function bindCatalogRepeater() {
		var $form = $("#sidrena-cijena-catalog-form");
		if (!$form.length) {
			return;
		}

		var i18n = typeof sidrenaCijenaAdmin !== "undefined" ? sidrenaCijenaAdmin.i18n || {} : {};
		var $json = $("#sidrena-cijena-catalog-json");
		var $tbody = $("#sidrena-cijena-catalog tbody");
		var $count = $("#sidrena-cijena-catalog-count");

		function escAttr(value) {
			return $("<div>").text(value == null ? "" : String(value)).html();
		}

		function emptyRow(partial) {
			return $.extend(
				{
					id: "",
					naziv: "",
					sku: "",
					barkod: "",
					marka: "",
					url: "",
					cijena_redovna: "",
					cijena_akcijska: "",
					naziv_posebnog_oblika: "",
					jedinica_mjere: "",
					cijena_jedinice_mjere: "",
					sidrena_cijena: "",
					sidrena_datum: "",
					vrsta: "proizvod",
					poslovnica: "",
					dostupnost: "dostupno",
					cjm: "",
					cjm_suffix: "",
				},
				partial || {}
			);
		}

		function iconButton(cls, icon, label, extraClass) {
			return (
				'<button type="button" class="sidrena-cijena-icon-btn ' +
				(extraClass || "") +
				" " +
				cls +
				'" title="' +
				escAttr(label) +
				'" aria-label="' +
				escAttr(label) +
				'">' +
				'<span class="dashicons ' +
				icon +
				'" aria-hidden="true"></span>' +
				"</button>"
			);
		}

		function shortcodeFor(id) {
			id = $.trim(String(id || ""));
			if (!id) {
				return "";
			}
			var prefix = typeof sidrenaCijenaAdmin !== "undefined" && sidrenaCijenaAdmin.catalogIdPrefix ? sidrenaCijenaAdmin.catalogIdPrefix : "";
			return '[sidrena-cijena id="' + prefix + id + '"]';
		}

		function shortcodeCell(id) {
			var code = shortcodeFor(id);
			if (!code) {
				return (
					'<td class="sidrena-cijena-catalog-shortcode"><span class="sidrena-cijena-catalog-shortcode__empty">' +
					escAttr(i18n.saveForCode || "Spremi cjenik za shortcode") +
					"</span></td>"
				);
			}
			return (
				'<td class="sidrena-cijena-catalog-shortcode">' +
				'<code class="js-shortcode">' +
				escAttr(code) +
				"</code>" +
				'<button type="button" class="sidrena-cijena-icon-btn sidrena-cijena-copy" data-copy="' +
				escAttr(code) +
				'" title="' +
				escAttr(i18n.copyShortcode || "Kopiraj shortcode") +
				'" aria-label="' +
				escAttr(i18n.copyShortcode || "Kopiraj shortcode") +
				'">' +
				'<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>' +
				"</button>" +
				"</td>"
			);
		}

		function cjmStored(row) {
			if (sidrenaCijenaAdmin && sidrenaCijenaAdmin.cjmEnabled) {
				return "";
			}
			return (
				'<input type="hidden" class="js-cjm-stored" value="' +
				escAttr(row.cjm === "yes" ? "yes" : "") +
				'" />' +
				'<input type="hidden" class="js-cjm-suffix" value="' +
				escAttr(row.cjm_suffix) +
				'" />'
			);
		}

		function cjmCell(row) {
			var enabled = !!(sidrenaCijenaAdmin && sidrenaCijenaAdmin.cjmEnabled);
			if (!enabled) {
				return "";
			}
			var on = row.cjm === "yes";
			return (
				'<td class="sidrena-cijena-cjm">' +
				'<label class="sidrena-cijena-catalog-cjm"><input type="checkbox" class="js-cjm sidrena-cijena-cjm-toggle" value="yes"' +
				(on ? " checked" : "") +
				" /> " +
				escAttr(i18n.cjm || "CJM") +
				"</label>" +
				'<span class="sidrena-cijena-cjm-suffix"' +
				(on ? "" : " hidden") +
				"><input type=\"text\" class=\"js-cjm-suffix\" placeholder=\"" +
				escAttr(i18n.cjmPlaceholder || "za kg/l") +
				'" value="' +
				escAttr(row.cjm_suffix) +
				'" /></span></td>'
			);
		}

		function vrstaCell(row) {
			var vrsta = row.vrsta === "usluga" ? "usluga" : "proizvod";
			return (
				'<td><select class="js-vrsta">' +
				'<option value="proizvod"' +
				(vrsta === "proizvod" ? " selected" : "") +
				">" +
				escAttr(i18n.vrstaProizvod || "Proizvod") +
				"</option>" +
				'<option value="usluga"' +
				(vrsta === "usluga" ? " selected" : "") +
				">" +
				escAttr(i18n.vrstaUsluga || "Usluga") +
				"</option>" +
				"</select></td>"
			);
		}

		function rowHtml(row) {
			row = emptyRow(row);
			return (
				"<tr>" +
				'<td><input type="hidden" class="js-id" value="' +
				escAttr(row.id) +
				'" />' +
				'<input type="hidden" class="js-poslovnica" value="' +
				escAttr(row.poslovnica) +
				'" />' +
				cjmStored(row) +
				'<input type="text" class="js-naziv regular-text" value="' +
				escAttr(row.naziv) +
				'" /></td>' +
				vrstaCell(row) +
				'<td><input type="text" class="js-sku" value="' +
				escAttr(row.sku) +
				'" /></td>' +
				'<td><input type="text" class="js-barkod" value="' +
				escAttr(row.barkod) +
				'" /></td>' +
				'<td><input type="text" class="js-marka" value="' +
				escAttr(row.marka) +
				'" /></td>' +
				'<td><input type="url" class="js-url" value="' +
				escAttr(row.url) +
				'" /></td>' +
				'<td><input type="text" class="js-regular" inputmode="decimal" value="' +
				escAttr(row.cijena_redovna) +
				'" /></td>' +
				'<td><input type="text" class="js-sale" inputmode="decimal" value="' +
				escAttr(row.cijena_akcijska) +
				'" /></td>' +
				'<td><input type="text" class="js-oblik" placeholder="' +
				escAttr(i18n.oblikPlaceholder || "akcija") +
				'" value="' +
				escAttr(row.naziv_posebnog_oblika) +
				'" /></td>' +
				'<td><input type="text" class="js-jedinica" placeholder="' +
				escAttr(i18n.jedinicaPlaceholder || "kg") +
				'" value="' +
				escAttr(row.jedinica_mjere) +
				'" /></td>' +
				'<td><input type="text" class="js-jedinica-cijena" inputmode="decimal" value="' +
				escAttr(row.cijena_jedinice_mjere) +
				'" /></td>' +
				'<td><input type="text" class="js-sidrena" inputmode="decimal" value="' +
				escAttr(row.sidrena_cijena) +
				'" /></td>' +
				'<td><input type="text" class="js-datum" placeholder="' +
				escAttr(i18n.datePlaceholder || "") +
				'" value="' +
				escAttr(row.sidrena_datum) +
				'" /></td>' +
				cjmCell(row) +
				'<td><select class="js-dostupnost">' +
				'<option value="dostupno"' +
				(row.dostupnost === "nedostupno" ? "" : " selected") +
				">" +
				escAttr(i18n.dostupno || "Dostupno") +
				"</option>" +
				'<option value="nedostupno"' +
				(row.dostupnost === "nedostupno" ? " selected" : "") +
				">" +
				escAttr(i18n.nedostupno || "Nedostupno") +
				"</option>" +
				"</select></td>" +
				shortcodeCell(row.id) +
				'<td class="sidrena-cijena-catalog-actions">' +
				iconButton("js-add", "dashicons-plus-alt2", i18n.addRow || "Dodaj redak") +
				iconButton("js-dup", "dashicons-admin-page", i18n.duplicate || "Dupliciraj") +
				iconButton("js-del", "dashicons-trash", i18n.remove || "Ukloni", "is-danger") +
				"</td>" +
				"</tr>"
			);
		}

		function collect() {
			var rows = [];
			$tbody.find("tr").each(function () {
				var $tr = $(this);
				rows.push({
					id: $.trim($tr.find(".js-id").val() || ""),
					naziv: $.trim($tr.find(".js-naziv").val() || ""),
					sku: $.trim($tr.find(".js-sku").val() || ""),
					barkod: $.trim($tr.find(".js-barkod").val() || ""),
					marka: $.trim($tr.find(".js-marka").val() || ""),
					url: $.trim($tr.find(".js-url").val() || ""),
					cijena_redovna: $.trim($tr.find(".js-regular").val() || ""),
					cijena_akcijska: $.trim($tr.find(".js-sale").val() || ""),
					naziv_posebnog_oblika: $.trim($tr.find(".js-oblik").val() || ""),
					jedinica_mjere: $.trim($tr.find(".js-jedinica").val() || ""),
					cijena_jedinice_mjere: $.trim($tr.find(".js-jedinica-cijena").val() || ""),
					sidrena_cijena: $.trim($tr.find(".js-sidrena").val() || ""),
					sidrena_datum: $.trim($tr.find(".js-datum").val() || ""),
					vrsta: $tr.find(".js-vrsta").val() === "usluga" ? "usluga" : "proizvod",
					poslovnica: $.trim($tr.find(".js-poslovnica").val() || ""),
					dostupnost: $tr.find(".js-dostupnost").val() === "nedostupno" ? "nedostupno" : "dostupno",
					cjm: $tr.find(".js-cjm").length
						? $tr.find(".js-cjm").is(":checked")
							? "yes"
							: ""
						: $.trim($tr.find(".js-cjm-stored").val() || "") === "yes"
						? "yes"
						: "",
					cjm_suffix: $.trim($tr.find(".js-cjm-suffix").val() || ""),
				});
			});
			return rows;
		}

		var PAGE_SIZE = 50;
		var $pager = $("#sidrena-cijena-catalog-pager");
		var $pageLabel = $("#sidrena-cijena-catalog-page");
		var $prev = $("#sidrena-cijena-catalog-prev");
		var $next = $("#sidrena-cijena-catalog-next");

		function updateCount() {
			if (!$count.length) {
				return;
			}
			var tpl = i18n.rowCount || "%d stavki";
			$count.text(tpl.replace("%d", String(viewRows.length)));
		}

		var $scope = $("#sidrena-cijena-catalog-scope");
		var scoped = $scope.length > 0;
		var allRows = [];
		var viewRows = [];
		var activeScope = "";
		var page = 1;
		var pageSlots = 0;

		function currentScope() {
			return scoped ? String($scope.val() || "") : "";
		}

		function blankRow() {
			return emptyRow({ poslovnica: currentScope() });
		}

		function pageCount() {
			return Math.max(1, Math.ceil(viewRows.length / PAGE_SIZE));
		}

		function updatePager() {
			var pages = pageCount();
			if (pages < 2) {
				$pager.attr("hidden", "hidden");
				return;
			}
			var tpl = i18n.pageStatus || "Stranica %1$d od %2$d";
			$pageLabel.text(tpl.replace("%1$d", String(page)).replace("%2$d", String(pages)));
			$prev.prop("disabled", page <= 1);
			$next.prop("disabled", page >= pages);
			$pager.removeAttr("hidden");
		}

		function renderPage() {
			var pages = pageCount();
			if (page > pages) {
				page = pages;
			}
			if (page < 1) {
				page = 1;
			}
			var start = (page - 1) * PAGE_SIZE;
			var slice = viewRows.slice(start, start + PAGE_SIZE);
			if (!slice.length) {
				pageSlots = 0;
				slice = [blankRow()];
			} else {
				pageSlots = slice.length;
			}
			$tbody.html(slice.map(rowHtml).join(""));
			updateCount();
			updatePager();
		}

		function outletCodes() {
			var list = (sidrenaCijenaAdmin && sidrenaCijenaAdmin.outlets) || [];
			return list.map(function (outlet) {
				return String(outlet.oznaka || "");
			});
		}

		function scopeMark(value) {
			var mark = String(value || "");
			if (mark.toLowerCase() === "webshop") {
				return "";
			}
			if (mark !== "" && outletCodes().indexOf(mark) === -1) {
				return "";
			}
			return mark;
		}

		function rowsFor(code) {
			return allRows.filter(function (row) {
				return scopeMark(row.poslovnica) === code;
			});
		}

		function syncViewFromAll() {
			var source = scoped ? rowsFor(activeScope) : allRows;
			viewRows = source.map(function (row) {
				return $.extend({}, row);
			});
		}

		function writeViewToAll() {
			if (!scoped) {
				allRows = viewRows.map(function (row) {
					return $.extend({}, row);
				});
				return;
			}
			var code = activeScope;
			var current = viewRows.map(function (row) {
				var copy = $.extend({}, row);
				copy.poslovnica = code;
				return copy;
			});
			var next = [];
			var inserted = false;
			allRows.forEach(function (row) {
				if (scopeMark(row.poslovnica) === code) {
					if (!inserted) {
						current.forEach(function (item) {
							next.push(item);
						});
						inserted = true;
					}
					return;
				}
				next.push($.extend({}, row));
			});
			if (!inserted) {
				current.forEach(function (item) {
					next.push(item);
				});
			}
			allRows = next;
		}

		function isBlankRow(row) {
			return (
				!$.trim(row.naziv) &&
				!$.trim(row.sku) &&
				!$.trim(row.barkod) &&
				!$.trim(row.marka) &&
				!$.trim(row.url) &&
				!$.trim(row.cijena_redovna) &&
				!$.trim(row.cijena_akcijska) &&
				!$.trim(row.naziv_posebnog_oblika) &&
				!$.trim(row.jedinica_mjere) &&
				!$.trim(row.cijena_jedinice_mjere) &&
				!$.trim(row.sidrena_cijena) &&
				!$.trim(row.sidrena_datum) &&
				row.vrsta !== "usluga" &&
				row.dostupnost !== "nedostupno" &&
				row.cjm !== "yes" &&
				!$.trim(row.cjm_suffix)
			);
		}

		function commitPage() {
			var current = collect();
			if (scoped) {
				current = current.map(function (row) {
					row.poslovnica = activeScope;
					return row;
				});
			}
			if (!pageSlots) {
				viewRows = current.filter(function (row) {
					return !isBlankRow(row);
				});
			} else {
				var start = (page - 1) * PAGE_SIZE;
				viewRows = viewRows.slice(0, start).concat(current, viewRows.slice(start + pageSlots));
			}
			writeViewToAll();
		}

		function showPageOf(index) {
			page = Math.floor(index / PAGE_SIZE) + 1;
			renderPage();
			var local = index - (page - 1) * PAGE_SIZE;
			$tbody.find("tr").eq(local).find(".js-naziv").trigger("focus");
		}

		try {
			allRows = JSON.parse($json.val() || "[]");
		} catch (e) {
			allRows = [];
		}
		if (!$.isArray(allRows)) {
			allRows = [];
		}
		activeScope = currentScope();
		syncViewFromAll();
		renderPage();

		$("#sidrena-cijena-catalog-add").on("click", function () {
			commitPage();
			viewRows.push(blankRow());
			writeViewToAll();
			showPageOf(viewRows.length - 1);
		});

		$tbody.on("click", ".js-add", function () {
			var index = $tbody.find("tr").index($(this).closest("tr"));
			commitPage();
			var at = (page - 1) * PAGE_SIZE + index + 1;
			viewRows.splice(at, 0, blankRow());
			writeViewToAll();
			showPageOf(at);
		});

		$tbody.on("click", ".js-dup", function () {
			var $tr = $(this).closest("tr");
			var index = $tbody.find("tr").index($tr);
			var copy = emptyRow({
				naziv: $tr.find(".js-naziv").val(),
				sku: $tr.find(".js-sku").val(),
				barkod: $tr.find(".js-barkod").val(),
				marka: $tr.find(".js-marka").val(),
				url: $tr.find(".js-url").val(),
				cijena_redovna: $tr.find(".js-regular").val(),
				cijena_akcijska: $tr.find(".js-sale").val(),
				naziv_posebnog_oblika: $tr.find(".js-oblik").val(),
				jedinica_mjere: $tr.find(".js-jedinica").val(),
				cijena_jedinice_mjere: $tr.find(".js-jedinica-cijena").val(),
				sidrena_cijena: $tr.find(".js-sidrena").val(),
				sidrena_datum: $tr.find(".js-datum").val(),
				vrsta: $tr.find(".js-vrsta").val(),
				poslovnica: currentScope() || $tr.find(".js-poslovnica").val(),
				dostupnost: $tr.find(".js-dostupnost").val(),
				cjm: $tr.find(".js-cjm").is(":checked") ? "yes" : $tr.find(".js-cjm-stored").val() || "",
				cjm_suffix: $tr.find(".js-cjm-suffix").val(),
			});
			commitPage();
			var at = (page - 1) * PAGE_SIZE + index + 1;
			viewRows.splice(at, 0, copy);
			writeViewToAll();
			showPageOf(at);
		});


		$tbody.on("input change", "input, select", function () {
			$(this).removeClass("has-error").removeAttr("aria-invalid");
			if (!$tbody.find(".has-error").length) {
				$form.find(".brendigo-catalog-validation").remove();
			}
		});

		$tbody.on("click", ".js-del", function () {
			var index = $tbody.find("tr").index($(this).closest("tr"));
			commitPage();
			var at = (page - 1) * PAGE_SIZE + index;
			if (viewRows.length) {
				viewRows.splice(at, 1);
			}
			writeViewToAll();
			renderPage();
		});

		$prev.on("click", function () {
			if (page <= 1) {
				return;
			}
			commitPage();
			page -= 1;
			renderPage();
		});

		$next.on("click", function () {
			if (page >= pageCount()) {
				return;
			}
			commitPage();
			page += 1;
			renderPage();
		});

		var $upitJson = $("#sidrena-cijena-upit-json");
		var $upitBody = $("#sidrena-cijena-upit tbody");
		var upitRows = [];

		function emptyUpit(partial) {
			return $.extend(
				{
					id: "",
					naziv: "",
					kriteriji: "",
					poslovnica: "",
				},
				partial || {}
			);
		}

		function blankUpit() {
			return emptyUpit({ poslovnica: currentScope() });
		}

		function upitHtml(row) {
			row = emptyUpit(row);
			return (
				"<tr>" +
				'<td><input type="hidden" class="js-id" value="' +
				escAttr(row.id) +
				'" />' +
				'<input type="hidden" class="js-poslovnica" value="' +
				escAttr(row.poslovnica) +
				'" />' +
				'<input type="text" class="js-naziv regular-text" value="' +
				escAttr(row.naziv) +
				'" /></td>' +
				'<td><input type="text" class="js-kriteriji regular-text" value="' +
				escAttr(row.kriteriji) +
				'" /></td>' +
				'<td class="sidrena-cijena-catalog-actions">' +
				iconButton("js-del", "dashicons-trash", i18n.remove || "Ukloni", "is-danger") +
				"</td>" +
				"</tr>"
			);
		}

		function collectUpit() {
			var rows = [];
			$upitBody.find("tr").each(function () {
				var $tr = $(this);
				rows.push({
					id: $.trim($tr.find(".js-id").val() || ""),
					naziv: $.trim($tr.find(".js-naziv").val() || ""),
					kriteriji: $.trim($tr.find(".js-kriteriji").val() || ""),
					poslovnica: $.trim($tr.find(".js-poslovnica").val() || ""),
				});
			});
			return rows;
		}

		function renderUpit(rows) {
			if (!rows.length) {
				rows = [blankUpit()];
			}
			$upitBody.html(rows.map(upitHtml).join(""));
		}

		function upitFor(code) {
			return upitRows.filter(function (row) {
				return scopeMark(row.poslovnica) === code;
			});
		}

		function commitUpit() {
			if (!scoped) {
				return;
			}
			var code = activeScope;
			var current = collectUpit().map(function (row) {
				row.poslovnica = code;
				return row;
			});
			var next = [];
			var inserted = false;
			upitRows.forEach(function (row) {
				if (scopeMark(row.poslovnica) === code) {
					if (!inserted) {
						current.forEach(function (item) {
							next.push(item);
						});
						inserted = true;
					}
					return;
				}
				next.push(row);
			});
			if (!inserted) {
				current.forEach(function (item) {
					next.push(item);
				});
			}
			upitRows = next;
		}

		function showUpit() {
			if (!$upitBody.length) {
				return;
			}
			renderUpit(scoped ? upitFor(activeScope) : upitRows);
		}

		try {
			upitRows = JSON.parse($upitJson.val() || "[]");
		} catch (e) {
			upitRows = [];
		}
		if (!$.isArray(upitRows)) {
			upitRows = [];
		}
		showUpit();

		$("#sidrena-cijena-upit-add").on("click", function () {
			$upitBody.append(upitHtml(blankUpit()));
			$upitBody.find("tr:last .js-naziv").trigger("focus");
		});

		$upitBody.on("click", ".js-del", function () {
			$(this).closest("tr").remove();
			if (!$upitBody.find("tr").length) {
				$upitBody.append(upitHtml(blankUpit()));
			}
		});

		$scope.on("change", function () {
			commitPage();
			commitUpit();
			activeScope = currentScope();
			page = 1;
			syncViewFromAll();
			renderPage();
			showUpit();
		});


		function requiredFieldsMissing(row) {
			var missing = [];
			if (!$.trim(row.naziv || "")) {
				missing.push("naziv");
			}
			if (!$.trim(row.cijena_redovna || "")) {
				missing.push("regular");
			}
			if (!$.trim(row.sidrena_cijena || "")) {
				missing.push("sidrena");
			}
			return missing;
		}

		function findInvalidRow() {
			var counters = {};
			for (var index = 0; index < allRows.length; index += 1) {
				var row = allRows[index];
				var scope = scopeMark(row.poslovnica);
				var scopeIndex = counters[scope] || 0;
				counters[scope] = scopeIndex + 1;
				if (isBlankRow(row)) {
					continue;
				}
				var missing = requiredFieldsMissing(row);
				if (missing.length) {
					return { row: row, scope: scope, scopeIndex: scopeIndex, missing: missing };
				}
			}
			return null;
		}

		function showValidationError(invalid) {
			if (!invalid) {
				return;
			}

			if (scoped && invalid.scope !== activeScope) {
				activeScope = invalid.scope;
				$scope.val(invalid.scope);
				syncViewFromAll();
				showUpit();
			}

			page = Math.floor(invalid.scopeIndex / PAGE_SIZE) + 1;
			renderPage();
			var local = invalid.scopeIndex - (page - 1) * PAGE_SIZE;
			var $row = $tbody.find("tr").eq(local);
			var selectors = {
				naziv: ".js-naziv",
				regular: ".js-regular",
				sidrena: ".js-sidrena",
			};

			$row.find("input, select").removeClass("has-error").removeAttr("aria-invalid");
			invalid.missing.forEach(function (key) {
				$row.find(selectors[key]).addClass("has-error").attr("aria-invalid", "true");
			});

			var message = i18n.catalogRequired || "Naziv, redovna cijena i sidrena cijena obavezni su za svaki popunjeni redak.";
			var $notice = $form.find(".brendigo-catalog-validation");
			if (!$notice.length) {
				$notice = $('<div class="notice notice-error inline brendigo-catalog-validation" role="alert"><p></p></div>');
				$form.find(".sidrena-cijena-catalog-toolbar").after($notice);
			}
			$notice.find("p").text(message);
			$row.find(".has-error").first().trigger("focus");
		}

		if ($form[0]) {
			$form[0].addEventListener(
				"submit",
				function (event) {
					commitPage();
					if (scoped) {
						commitUpit();
					}
					var invalid = findInvalidRow();
					if (!invalid) {
						$form.find(".brendigo-catalog-validation").remove();
						return;
					}
					event.preventDefault();
					event.stopPropagation();
					showValidationError(invalid);
				},
				true
			);
		}

		$form.on("submit", function () {
			commitPage();
			if (scoped) {
				commitUpit();
				$upitJson.val(JSON.stringify(upitRows));
			} else {
				$upitJson.val(JSON.stringify(collectUpit()));
			}
			$json.val(JSON.stringify(allRows));
		});
	}

	function bindWooBulkEditor() {
		var $form = $("#sidrena-wc-catalog-form");
		if (!$form.length) {
			return;
		}

		var i18n = typeof sidrenaCijenaAdmin !== "undefined" ? sidrenaCijenaAdmin.i18n || {} : {};
		var $rows = $form.find(".brendigo-woo-row");
		var $status = $("#sidrena-wc-change-status");
		var $saveButton = $form.find(".js-sidrena-wc-save");
		var $resetButton = $form.find(".js-sidrena-wc-reset");
		var dirtyCount = 0;
		var submitting = false;

		function rowState($row) {
			var state = [];
			$row.find(":input[name]").each(function () {
				state.push(String($(this).val() == null ? "" : $(this).val()));
			});
			return JSON.stringify(state);
		}

		$rows.each(function () {
			$(this).data("sidrenaInitial", rowState($(this)));
		});

		function updateChangeStatus() {
			var changed = 0;
			$rows.each(function () {
				var $row = $(this);
				var dirty = rowState($row) !== $row.data("sidrenaInitial");
				$row.toggleClass("is-dirty", dirty);
				if (dirty) {
					changed += 1;
				}
			});

			dirtyCount = changed;
			$saveButton.prop("disabled", changed === 0);
			$resetButton.prop("disabled", changed === 0);

			if (!$status.length) {
				return;
			}

			if (!changed) {
				$status.text(i18n.wooNoChanges || "Nema nespremljenih promjena.");
				return;
			}

			var template = i18n.wooChangedRows || "%d proizvoda ima nespremljene promjene.";
			$status.text(template.replace("%d", changed));
		}

		$form.on("input change", ".brendigo-woo-row :input", updateChangeStatus);

		function visibleRows() {
			return $rows.filter(function () {
				return !$(this).is("[hidden]");
			});
		}

		function applyFilters() {
			var query = $.trim($("#sidrena-wc-filter").val() || "").toLowerCase();
			var priceFilter = String($("#sidrena-wc-price-filter").val() || "all");
			var visible = 0;
			$rows.each(function () {
				var $row = $(this);
				var haystack = String($row.data("search") || "").toLowerCase();
				var hasPrice = !!$.trim($row.find(".js-sidrena-wc-price").val() || "");
				$row.toggleClass("is-missing-anchor", !hasPrice);
				var searchMatch = !query || haystack.indexOf(query) !== -1;
				var priceMatch = priceFilter === "all" || (priceFilter === "missing" && !hasPrice) || (priceFilter === "complete" && hasPrice);
				var show = searchMatch && priceMatch;
				$row.attr("hidden", !show);
				if (show) {
					visible += 1;
				}
			});
			$form.find(".brendigo-woo-group").attr("hidden", query || priceFilter !== "all" ? true : false);
			var visibleText = (i18n.wooVisibleRows || "Prikazano {visible} od {total} stavki na ovoj stranici.")
				.replace("{visible}", visible)
				.replace("{total}", $rows.length);
			$("#sidrena-wc-visible-status").text(visibleText);
		}

		$("#sidrena-wc-filter").on("input", applyFilters);
		$("#sidrena-wc-price-filter").on("change", applyFilters);
		$form.on("change", ".js-sidrena-wc-price", applyFilters);

		$(".js-sidrena-wc-fill-date").on("click", function () {
			var date = $.trim($("#sidrena-wc-bulk-date").val() || "");
			if (!date) {
				return;
			}
			visibleRows().find(".js-sidrena-wc-date").each(function () {
				if (!$.trim($(this).val() || "")) {
					$(this).val(date).trigger("change");
				}
			});
		});

		$(".js-sidrena-wc-fill-price").on("click", function () {
			var message = i18n.fillCurrentConfirm || "Fill empty reference-price fields with the current price? Use this only if that price applied on the reference date.";
			if (!window.confirm(message)) {
				return;
			}
			visibleRows().each(function () {
				var $row = $(this);
				var $field = $row.find(".js-sidrena-wc-price");
				if ($.trim($field.val() || "")) {
					return;
				}
				var price = $.trim($row.find(".brendigo-woo-current").data("current") || "");
				if (price) {
					$field.val(price).trigger("change");
				}
			});
		});

		$resetButton.on("click", function () {
			if (!dirtyCount) {
				return;
			}

			var message = i18n.wooResetConfirm || "Discard all unsaved changes on this page?";
			if (!window.confirm(message)) {
				return;
			}

			$rows.each(function () {
				var $row = $(this);
				var initial;
				try {
					initial = JSON.parse(String($row.data("sidrenaInitial") || "[]"));
				} catch (error) {
					initial = [];
				}

				$row.find(":input[name]").each(function (index) {
					if (typeof initial[index] !== "undefined") {
						$(this).val(initial[index]);
					}
				});
			});

			applyFilters();
			updateChangeStatus();
		});

		$(window).on("beforeunload.sidrenaWooBulk", function (event) {
			if (submitting || dirtyCount < 1) {
				return undefined;
			}
			var warning = i18n.wooLeaveWarning || "You have unsaved reference-price changes. Leaving this page will discard them.";
			event.preventDefault();
			event.originalEvent.returnValue = warning;
			return warning;
		});

		$form.on("submit", function (event) {
			if (dirtyCount < 1) {
				event.preventDefault();
				return;
			}

			submitting = true;
			$saveButton.prop("disabled", true);
			// Serialize only changed product rows into one JSON field. This avoids
			// max_input_vars truncation on variable products with many variations.
			var changedRows = {};
			$rows.each(function () {
				var $row = $(this);
				if (rowState($row) === $row.data("sidrenaInitial")) {
					return;
				}

				var productId = String($row.attr("data-product-id") || "");
				if (!/^\d+$/.test(productId)) {
					return;
				}

				changedRows[productId] = {
					price: String($row.find(".js-sidrena-wc-price").val() || ""),
					date: String($row.find(".js-sidrena-wc-date").val() || ""),
					barcode: String($row.find('input[name$="[barcode]"]').val() || ""),
					visibility: String($row.find('select[name$="[visibility]"]').val() || "auto")
				};
			});

			$("#sidrena-wc-json").val(JSON.stringify(changedRows));
			$rows.find(":input[name]").prop("disabled", true);
		});

		updateChangeStatus();
		applyFilters();
	}

	$(function () {
		bindCatalogRepeater();
		bindWooBulkEditor();
	});
})(jQuery);
