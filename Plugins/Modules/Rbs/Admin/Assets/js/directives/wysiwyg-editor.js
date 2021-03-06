(function ($, rangy) {

	"use strict";

	var app = angular.module('RbsChange');


	app.directive('rbsWysiwygEditor', ['$timeout', '$document', '$q', '$rootScope', 'RbsChange.i18n', 'RbsChange.Utils', function ($timeout, $document, $q, $rootScope, i18n, Utils)
	{
		var tools =
		{
			h1 : {
				display : "H1",
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_heading_1'),
				block : true
			},
			h2 : {
				display : "H2",
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_heading_2'),
				block : true
			},
			h3 : {
				display : "H3",
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_heading_3'),
				block : true
			},
			h4 : {
				display : "H4",
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_heading_4'),
				block : true
			},
			p : {
				display : "¶",
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_paragraph'),
				block : true
			},
			pre : {
				display : "pre",
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_pre'),
				block : true
			},
			blockquote : {
				display : '<i class="icon-quote-right"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_quote'),
				block : true
			},
			insertUnorderedList : {
				display : '<i class="icon-list-ul"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_unordered_list')
			},
			insertOrderedList : {
				display : '<i class="icon-list-ol"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_ordered_list')
			},
			undo : {
				display : '<i class="icon-undo"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_undo')
			},
			redo : {
				display : '<i class="icon-repeat"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_redo')
			},
			bold : {
				display : '<i class="icon-bold"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_bold')
			},
			justifyLeft : {
				display : '<i class="icon-align-left"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_align_left')
			},
			justifyRight : {
				display : '<i class="icon-align-right"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_align_right')
			},
			justifyCenter : {
				display : '<i class="icon-align-center"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_align_center')
			},
			italic : {
				display : '<i class="icon-italic"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_italic')
			},
			underline : {
				display : '<i class="icon-underline"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_underline')
			},
			removeFormat : {
				display : '<i class="icon-ban-circle"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_clear')
			},
			insertImage : {
				display : '<i class="icon-picture"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_insert_picture'),
				action : function (scope) {
					scope.selectImage();
				}
			},
			insertExternalLink : {
				display : '<i class="icon-external-link"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_insert_external_link'),
				action : function (scope) {
					var urlLink = window.prompt(i18n.trans('m.rbs.admin.admin.enter_external_url'), 'http://');
					if (urlLink) {
						scope.wrapSelection('createLink', urlLink);
					}
				}
			},
			insertLink : {
				display : '<i class="icon-link"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_insert_link'),
				action : function (scope) {
					scope.selectLink();
				}
			},
			unlink : {
				display : '<i class="icon-unlink"></i>',
				title : i18n.trans('m.rbs.admin.admin.wysiwyg_remove_link')
			}
		};


		return {
			restrict : 'E',
			require : 'ngModel',
			scope : true,
			template :
				'<div class="btn-toolbar">' +
					'<div class="btn-group pull-right" caption="' + i18n.trans('m.rbs.admin.admin.wysiwyg_tools') + '" style="margin-right: 5px;">' +
						'<button type="button" ng-disabled="sourceView" class="btn btn-default btn-sm" title="(= getButtonTooltip(\'undo\') =)" ng-bind-html="getButtonLabel(\'undo\')" ng-click="runTool(\'undo\')"></button>' +
						'<button type="button" ng-disabled="sourceView" class="btn btn-default btn-sm" title="(= getButtonTooltip(\'redo\') =)" ng-bind-html="getButtonLabel(\'redo\')" ng-click="runTool(\'redo\')"></button>' +
						'<button class="btn btn-default btn-sm" type="button" ng-class="{\'active\': sourceView}" ng-click="toggleViewSource()"><i class="icon-code"></i></button>' +
					'</div>' +
					'<div class="btn-group" ng-repeat="group in toolbarConfig" caption="(= group.label =)">' +
						'<button type="button" class="btn btn-default btn-sm" ng-repeat="tool in group.tools"' +
						' title="(= getButtonTooltip(tool) =)"' +
						' ng-bind-html="getButtonLabel(tool)"' +
						' ng-class="{\'active\': toolIsActive(tool)}"' +
						' ng-disabled="sourceView"' +
						' ng-click="runTool(tool)"></button>' +
					'</div>' +
				'</div>' +
				'<div ng-hide="sourceView" contenteditable="true" style="min-height: 150px; height: auto;" class="form-control"></div>' +
				'<textarea ng-show="sourceView" ng-model="source" style="min-height: 150px; height: auto;" class="form-control"></textarea>',

			link : function (scope, element, attrs, ngModel)
			{
				var opts = {},
				    editableEl = element.find('[contenteditable]'),
					sourceEl = element.find('textarea');

				scope.tools = angular.copy(tools);
				scope.toolbarConfig = [
					{
						label : i18n.trans('m.rbs.admin.admin.wysiwyg_blocks'),
						tools : ['h1', 'h2', 'h3', 'h4', 'p', 'blockquote', 'pre']
					},
					{
						label : i18n.trans('m.rbs.admin.admin.wysiwyg_lists'),
						tools : ['insertUnorderedList', 'insertOrderedList']
					},
					{
						label : i18n.trans('m.rbs.admin.admin.wysiwyg_format'),
						tools : ['bold', 'italic', 'underline', 'removeFormat']
					},
					{
						label : i18n.trans('m.rbs.admin.admin.wysiwyg_alignment'),
						tools : ['justifyLeft','justifyCenter','justifyRight']
					},
					{
						label : i18n.trans('m.rbs.admin.admin.wysiwyg_insertion'),
						tools : ['insertImage', 'insertLink', 'insertExternalLink', 'unlink']
					}
				];


				scope.getButtonLabel = function (item)
				{
					return scope.tools.hasOwnProperty(item) ? scope.tools[item].display : item;
				};


				scope.getButtonTooltip = function (item)
				{
					return scope.tools.hasOwnProperty(item) ? scope.tools[item].title : '';
				};


				scope.toolIsActive = function (item)
				{
					return scope.tools.hasOwnProperty(item) ? (scope.tools[item].active || false) : false;
				};


				scope.runTool = function (toolId)
				{
					var tool = scope.tools[toolId];
					if (angular.isFunction(tool.action)) {
						tool.action(scope);
					} else {
						if (tool.block) {
							scope.wrapSelection("formatBlock", '<' + toolId.toUpperCase() + '>');
						} else {
							scope.wrapSelection(toolId);
						}
					}
					updateSelectedStyles();
				};


				scope.wrapSelection = function (command, options)
				{
					$document[0].execCommand(command, false, options || null);
				};


				scope.queryState = function (toolId)
				{
					var tool = scope.tools[toolId];
					if (tool && tool.block) {
						return $document[0].queryCommandValue('formatBlock').toLowerCase() === toolId.toLowerCase();
					} else {
						return $document[0].queryCommandState(toolId);
					}
				};


				scope.selectLink = function ()
				{
					var range = rangy.saveSelection();
					scope.$emit('WYSIWYG.SelectLink', {
						range : range,
						contents : editableEl.html()
					});
				};


				scope.selectImage = function ()
				{
					var range = rangy.saveSelection();
					scope.$emit('WYSIWYG.SelectImage', {
						range : range,
						contents : editableEl.html()
					});
				};


				scope.$on('WYSIWYG.InsertLink', function (event, data)
				{
					editableEl.html(data.contents);
					refocus().then(function ()
					{
						rangy.restoreSelection(data.range);

						// Whole A element
						if (data.html.trim().charAt(0) === '<') {
							scope.wrapSelection('insertHTML', data.html);
						}
						// URL
						else {
							scope.wrapSelection('createLink', data.html);
						}
					});
				});


				scope.$on('WYSIWYG.InsertImage', function (event, data)
				{
					editableEl.html(data.contents);
					refocus().then(function ()
					{
						rangy.restoreSelection(data.range);
						scope.wrapSelection('insertHTML', data.html);
					});
				});


				scope.sourceView = false;

				scope.toggleViewSource = function ()
				{
					if (scope.sourceView) {
						scope.sourceView = false;
						refocus();
					} else {
						scope.sourceView = true;
						updateSourceView(editableEl.html());
						refocus();
					}
				};


				// view -> model
				editableEl.on('input', function()
				{
					updateNgModel();
					commitNgModel();
				});


				function updateNgModel ()
				{
					var html = editableEl.html();
					ngModel.$setViewValue(html);
					if (html === '') {
						// the cursor disappears if the contents is empty
						// so we need to refocus
						refocus(editableEl);
					}
				}


				function refocus (el)
				{
					if (! el) {
						el = scope.sourceView ? sourceEl : editableEl;
					}
					return $timeout(function() {
						el[0].blur();
						el[0].focus();
					},100);
				}


				// model -> view
				var oldRender = ngModel.$render;
				ngModel.$render = function()
				{
					if (!!oldRender) {
						oldRender();
					}
					editableEl.html(ngModel.$viewValue || '');
				};


				// Select non-editable elements
				editableEl.bind('click', function(e) {
					var range, sel, target;
					target = e.toElement;
					if (target !== this && angular.element(target).attr('contenteditable') === 'false') {
						range = document.createRange();
						sel = window.getSelection();
						range.setStartBefore(target);
						range.setEndAfter(target);
						sel.removeAllRanges();
						sel.addRange(range);
					}
				});


				// Watch changes in the HTML source view to update the WYSIWYG view.
				scope.$watch('source', function (source)
				{
					if (angular.isDefined(source)) {
						// Remove new line characters after each block tag.
						editableEl.html(source.replace(/<\/(p|h[1-6]|pre|ul|ol|blockquote)>\n+/g, '</$1>'));
					}
				});


				function updateSourceView(html)
				{
					// Add new line characters after each block tag.
					scope.source = html.replace(/<\/(p|h[1-6]|pre|ul|ol|blockquote)>/g, "</$1>\n\n");
				}


				editableEl.on('keydown', updateSelectedStyles);
				editableEl.on('keyup', updateSelectedStyles);
				editableEl.on('mouseup', updateSelectedStyles);

				editableEl.on('dblclick', 'img[data-document-id]', openImageSettings);


				function openImageSettings (event)
				{
					var img = $(event.target),
						width, height;

					// Well, this is not very sexy with a prompt, but it does the job for the moment.
					width = window.prompt(i18n.trans('m.rbs.admin.admin.wysiwyg_enter_image_width | ucf'));
					if (width) {
						width = parseInt(width, 10);
					}

					height = window.prompt(i18n.trans('m.rbs.admin.admin.wysiwyg_enter_image_height | ucf'));
					if (height) {
						height = parseInt(height, 10);
					}

					if (width && ! isNaN(width) && height && ! isNaN(height)) {
						img.attr('data-resize-width', width)
						   .attr('data-resize-height', height)
						   .attr('src', Utils.makeUrl(img.attr('src'), { maxWidth: width, maxHeight: height }))
						   .attr('title', width+'x'+height);
						img.css({'max-width' : ''});
						updateNgModel();
						commitNgModel();
					}
				}


				// Updates buttons active state.
				function updateSelectedStyles ()
				{
					angular.forEach(scope.toolbarConfig, function (group)
					{
						angular.forEach(group.tools, function (toolId)
						{
							var tool = scope.tools[toolId];
							if (angular.isFunction(tool.activeState)) {
								tool.active = tool.activeState(scope);
							} else {
								tool.active = scope.queryState(toolId);
							}
						});
					});
					commitNgModel();
				}


				// Calls Angular's digest cycle if needed.
				function commitNgModel ()
				{
					if (! $rootScope.$$phase) {
						scope.$apply();
					}
				}

			}
		};
	}]);

})(window.jQuery, rangy);