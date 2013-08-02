(function ()
{
	"use strict";

	var app = angular.module('RbsChange');

	/**
	 * Controller for list.
	 *
	 * @param $scope
	 * @param Breadcrumb
	 * @param MainMenu
	 * @param i18n
	 * @constructor
	 */
	function ListController($scope, Breadcrumb, MainMenu, i18n)
	{
		Breadcrumb.resetLocation([
			[i18n.trans('m.rbs.catalog.admin.js.module-name | ucf'), "Rbs/Catalog"],
			[i18n.trans('m.rbs.catalog.admin.js.attribute-list | ucf'), "Rbs/Catalog/Attribute/"]
		]);

		MainMenu.loadModuleMenu('Rbs_Catalog');
	}

	ListController.$inject = ['$scope', 'RbsChange.Breadcrumb', 'RbsChange.MainMenu', 'RbsChange.i18n'];
	app.controller('Rbs_Catalog_Attribute_ListController', ListController);

	/**
	 * Controller for form.
	 *
	 * @param $scope
	 * @param Breadcrumb
	 * @param FormsManager
	 * @param i18n
	 * @constructor
	 */
	function FormController($scope, Breadcrumb, FormsManager, i18n)
	{
		Breadcrumb.setLocation([
			[i18n.trans('m.rbs.catalog.admin.js.module-name | ucf'), "Rbs/Catalog"],
			[i18n.trans('m.rbs.catalog.admin.js.attribute-list | ucf'), "Rbs/Catalog/Attribute/"]
		]);
		FormsManager.initResource($scope, 'Rbs_Catalog_Attribute');
	}

	FormController.$inject = ['$scope', 'RbsChange.Breadcrumb', 'RbsChange.FormsManager', 'RbsChange.i18n'];
	app.controller('Rbs_Catalog_Attribute_FormController', FormController);


	app.directive('rbsAttributeEditor', ['RbsChange.REST', 'RbsChange.Utils', rbsAttributeEditorDirective]);

	function rbsAttributeEditorDirective (REST, Utils) {

		return {
			restrict : 'E',
			scope : {attributeValues: "=", attributeEditor: "="},
			templateUrl : "Rbs/Catalog/Attribute/attributeEditor.twig",

			link : function (scope, elm, attrs) {

				scope.$watch('attributeEditor', function (value, oldvalue) {
					if (value !== oldvalue)
					{
						var attrId = Utils.isDocument(value) ? value.id : parseInt(value, 10);
						if (!isNaN(attrId))
						{
							REST.resource('Rbs_Catalog_Attribute', attrId).then(generateEditor, clearEditor);
						}
						else
						{
							clearEditor();
						}
					}
				});

				scope.$watch('attributeValues', function (value, oldvalue) {
					if (value !== oldvalue)
					{
						if (angular.isArray(scope.attributes))
						{
							assocValues(scope.attributes);
						}
					}
				}, true);

				function clearEditor() {
					scope.attributes = [];
				}

				function generateEditor(attribute) {
					var editorDefinition = attribute.editorDefinition;
					if (angular.isObject(editorDefinition))
					{
						scope.attributes = editorDefinition.attributes;
						if (angular.isArray(scope.attributeValues))
						{
							assocValues(scope.attributes);
						}
					}
				}

				function assocValues(attributes) {
					for (var i = 0; i < attributes.length; i++)
					{
						if (attributes[i].attributes)
						{
							assocValues(attributes[i].attributes)
						}
						else
						{
							attributes[i].value = getAttributeValue(attributes[i]);
						}
					}
				}

				function getAttributeValue(attribute) {
					if (angular.isArray(scope.attributeValues))
					{
						for (var i = 0; i < scope.attributeValues.length; i++)
						{
							if (scope.attributeValues[i].id == attribute.id)
							{
								return scope.attributeValues[i];
							}
						}
						var v = {id: attribute.id, valueType: attribute.valueType, value: attribute.defaultValue};
						scope.attributeValues.push(v);
						return v;
					}
				}
			}
		}
	}

	app.directive('rbsAttributeItem', ['RbsChange.Utils', rbsAttributeItemDirective]);

	function rbsAttributeItemDirective (Utils) {

		return {
			restrict : 'E',
			scope : {attribute: "="},
			templateUrl : "Rbs/Catalog/Attribute/attributeItem.twig",
			link : function (scope, elm, attrs) {

			}
		}
	}
})();