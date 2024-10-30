(function () {
	tinymce.create('tinymce.plugins.bwTerms', {
		getTerms: function (success) {
			var ajaxPreferredTermsUrl = this.editor.getParam("bw_ajax_get_preferred_terms", false);
			var plugin = this;

			if (ajaxPreferredTermsUrl == false) {
				this.editor.setProgressState(0);
				return;
			}
			tinymce.util.XHR.send({
				url: ajaxPreferredTermsUrl,
				content_type: 'application/x-www-form-urlencoded',
				type: "POST",
				async: true,
				success: success,
				error: function (type, req, o) {

				}
			});
		},
		getScore: function (data, success) {

			var ajaxGetScoreUrl = this.editor.getParam("bw_ajax_get_score", false);
			var plugin = this;

			if (ajaxGetScoreUrl == false) {
				this.editor.setProgressState(0);
				return;
			}

			tinymce.util.XHR.send({
				url: ajaxGetScoreUrl,
				content_type: 'application/x-www-form-urlencoded',
				type: "POST",
				data: "content=" + encodeURI(data).replace(/&/g, '%26'),
				async: true,
				success: success
			});

		},
		tidyUP: function () {
			var plugin = this;
			var node;
			tinymce.each(this.findSpans(node).reverse(), function (n) {
				if (n && (plugin.editor.dom.hasClass(n, 'bwSuggestion')) || plugin.editor.dom.hasClass(n, 'mceItemHidden') || plugin.isEmptySpan(n)) {
					if (n.innerHTML == '&nbsp;') {
						var nnode = document.createTextNode(' '); /* hax0r */
						plugin.editor.dom.replace(n, nnode);
					} else {
						plugin.editor.dom.remove(n, 1);
					}
				}
			});
			plugin.editor.dom.setHTML(plugin.editor.dom.getRoot(), plugin.editor.dom.getRoot().innerHTML);

		},
		isEmptySpan: function (node) {
			return (this.getAttrib(node, 'class') == "" && this.getAttrib(node, 'style') == "" && this.getAttrib(node, 'id') == "" && !this.editor.dom.hasClass(node, 'Apple-style-span') && this.getAttrib(node, 'mce_name') == "");
		},
		getAttrib: function (node, key) {
			return this.editor.dom.getAttrib(node, key);
		},
		findSpans: function (parent) {
			if (parent == undefined) {
				return this.editor.dom.select('span');
			} else {
				return this.editor.dom.select('span', parent);
			}
		},
		nodeWalk: function (node, bt, pt) {
			var plugin = this;
			for (var i = 0; i < node.length; i++) {
				if (node[i].hasChildNodes() && 'SCRIPT' !== node[i].nodeName && !(plugin.editor.dom.hasClass(node[i], 'bwSuggestion'))) {
					plugin.nodeWalk(node[i].childNodes, bt, pt);
				}
				if (3 === node[i].nodeType && node[i].nodeValue != "") {
					var bwRegex = new RegExp("\\b" + plugin.prepString(bt) + "\\b", 'gi');
					if (bwRegex.test(node[i].nodeValue)) {
						nodeHTML = node[i].nodeValue.replace(bwRegex, '<span class="bwSuggestion" data-preferred="' + pt + '" style="border-bottom: dotted 1px green;">' + bt + '</span>');
						nnode = plugin.editor.dom.create('span', {
							'class': 'mceItemHidden'
						}, nodeHTML);
						plugin.editor.dom.replace(nnode, node[i]);
					}
				}
			}
		},
		prepString: function (str) {
			return (str + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, "\\$1");
		},
		markWords: function (bwPreferredTerms) {
			var content = this.editor.getBody();
			var nodes = content.childNodes;
			for (var pt = 0; pt < bwPreferredTerms.length; pt++) {
				bw_bad_terms = bwPreferredTerms[pt]['non_preferred'].split(",");
				for (var bt = 0; bt < bw_bad_terms.length; bt++) {
					if (bw_bad_terms[bt] != "" && bw_bad_terms[bt] != " ") {
						content = this.editor.getBody();
						nodes = content.childNodes;
						this.nodeWalk(nodes, bw_bad_terms[bt], bwPreferredTerms[pt]['preferred']);
					}

				}
			}
		},
		init: function (ed, url) {
			var plugin = this;
			var editor = ed;
			this.url = url;
			this.editor = ed;
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');

			plugin.getTerms(function (data, request, someObject) {
				if (request.status != 200) {
					return;
				}
				bwPreferredTerms = JSON.parse(request.responseText);
				if(!bwPreferredTerms){
					bwPreferredTerms = 0;
				}

				ed.addCommand('bwCheckTerms', function () {
					var menu = plugin._menu;
					if (typeof menu === 'undefined' || typeof menu.isMenuVisible === 'undefined' || menu.isMenuVisible == 0) {
						var bm = tinyMCE.activeEditor.selection.getBookmark();
						plugin.tidyUP();
						if(bwPreferredTerms){
							plugin.markWords(bwPreferredTerms);
						}
						tinyMCE.activeEditor.selection.moveToBookmark(bm);
					}
				});

				tinyMCE.activeEditor.execCommand('bwCheckTerms');
			});

			ed.addCommand('bwUpdateScore', function () {
				plugin.getScore(ed.getContent(), function (data, request, someObject) {
					if (request.status != 200) {
						return;
					}
					var meta_box = document.getElementById("bw_readability_meta");
					inside = meta_box.getElementsByClassName('inside');
					inside[0].innerHTML = request.responseText;
				});
			});
			//setup before functions
			var typingTimer; //timer identifier
			var doneTypingInterval = 1000; //time in ms, 1 second for example
			ed.onKeyPress.add(function (ed, e) {
				clearTimeout(typingTimer);
				typingTimer = setTimeout(function () {
					bookmark = tinyMCE.activeEditor.selection.getBookmark();
					tinyMCE.activeEditor.execCommand('bwUpdateScore');
					tinyMCE.activeEditor.execCommand('bwCheckTerms');
					tinyMCE.activeEditor.selection.moveToBookmark(bookmark);
				}, doneTypingInterval);
			});
			editor.onClick.add(plugin.createMenu, plugin);
			editor.onContextMenu.add(plugin.createMenu, plugin);
			editor.onPreProcess.add(function (sender, object) {
				var dom = sender.dom;
				tinymce.each(dom.select('span', object.node).reverse(), function (n) {
					if (n && (dom.hasClass(n, 'bwSuggestion') || dom.hasClass(n, 'mceItemHidden') || (dom.getAttrib(n, 'class') == "" && dom.getAttrib(n, 'style') == "" && dom.getAttrib(n, 'id') == "" && !dom.hasClass(n, 'Apple-style-span') && dom.getAttrib(n, 'mce_name') == ""))) {
						dom.remove(n, 1);
					}
				});
			});
			editor.onBeforeExecCommand.add(function (editor, command) {
				if (command == 'mceCodeEditor') {
					plugin.tidyUP();
				}
			});
		},
		createMenu: function (ed, e) {
			var ed = this.editor,
				menu = this._menu,
				p1,
				dom = ed.dom,
				vp = dom.getViewPort(ed.getWin());
			var plugin = this;

			if (!menu) {
				p1 = dom.getPos(ed.getContentAreaContainer());
				menu = ed.controlManager.createDropMenu('bwSuggestionMenu', {
					offset_x: p1.x,
					offset_y: p1.y
				});
				plugin._menu = menu;
			}
			if (plugin.editor.dom.hasClass(e.target, 'bwSuggestion')) {
				/* remove these other lame-o elements */
				menu.removeAll();

				/* find the correct suggestions object */
				var suggestions = plugin.findSuggestion(e.target);
				menu.add({
					title: 'Preferred Terms',
					'class': 'mceMenuItemTitle'
				}).setDisabled(1);

				for (var i = 0; i < suggestions.length; i++) {
					(function (sugg) {
						menu.add({
							title: sugg,
							onclick: function () {
								plugin.applySuggestion(e.target, sugg);
							}
						});
					})(suggestions[i]);
				}
				menu.addSeparator();

				/* show the menu please */
				ed.selection.select(e.target);
				p1 = dom.getPos(e.target);
				menu.showMenu(p1.x, p1.y + e.target.offsetHeight - vp.y);

				return tinymce.dom.Event.cancel(e);
			} else {
				menu.hideMenu();
			}
		},
		findSuggestion: function (element) {
			var suggestions = new Array();
			suggestions = this.getAttrib(element, 'data-preferred').split(",");
			return suggestions;
		},
		applySuggestion: function (node, sugg) {
			var suggestion = this.editor.dom.create('span', {
				'class': 'mceItemHidden'
			}, sugg);
			this.editor.dom.replace(suggestion, node);
			this.editor.dom.remove(node, 1);
		},
		createControl: function (n, cm) {
			return null;
		},
		getInfo: function () {
			return {
				longname: 'Readability Control -Preferred Terms',
				author: 'Mike',
				authorurl: 'http://www.google.com',
				infourl: 'http://www.google.com',
				version: tinymce.majorVersion + "." + tinymce.minorVersion
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('bwTerms', tinymce.plugins.bwTerms);
})();