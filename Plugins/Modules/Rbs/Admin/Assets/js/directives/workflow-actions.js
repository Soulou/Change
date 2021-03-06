(function () {

	"use strict";

	var app = angular.module('RbsChange');

	app.directive('rbsDocumentWorkflowActions', ['$timeout', '$q', 'RbsChange.REST', 'RbsChange.Utils', 'RbsChange.i18n', '$location', function ($timeout, $q, REST, Utils, i18n, $location) {

		return {
			restrict : 'A',
			replace  : true,
			templateUrl : 'Rbs/Admin/js/directives/workflow-actions.twig',

			scope : {
				'document' : '=',
				'modelInfo' : '=documentModel',
				'onClose' : '&'
			},

			link : function (scope, element, attrs) {

				var	lastUpdatedDoc = null,
					oldCssClass = null;

				scope.data = {
					rejectReason : '',
					contentAction : 'accept',
					action : ''
				};

				scope.closable = element.is('[on-close]');


				function freezeUI () {
					element.find('button').attr('disabled', 'disabled');
				}


				function unfreezeUI (error) {
					element.find('button').removeAttr('disabled');
					scope.data.progress = undefined;

					if (error) {
						scope.data.error = error;
					}
					else {
						scope.data.error = null;
					}

					if (lastUpdatedDoc) {
						angular.extend(scope.document, lastUpdatedDoc);
						lastUpdatedDoc = null;
					}
				}


				function accept (actionName, params) {
					freezeUI();
					REST.executeTaskByCodeOnDocument(actionName, scope.document, params).then(
						// Success
						function (doc) {
							lastUpdatedDoc = doc;
							unfreezeUI();
						},
						// Error
						unfreezeUI
					);
				}


				function reject (actionName, reason) {
					freezeUI();
					REST.executeTaskByCodeOnDocument(actionName, scope.document, {'reason': reason}).then(
						// Success
						function (doc) {
							lastUpdatedDoc = doc;
							unfreezeUI();
						},
						// Error
						unfreezeUI
					);
				}


				function publicationDatesChanged () {
					return ! angular.equals(scope.data.startPublication, scope.document.startPublication) || ! angular.equals(scope.data.endPublication, scope.document.endPublication);
				}


				function doSubmit () {
					if (scope.data.action === 'contentValidation' && scope.data.contentAction === 'reject') {
						reject(scope.data.action, scope.data.rejectReason);
					}
					else {
						accept(scope.data.action);
					}
				}


				scope.$watch('document', function documentChanged (doc) {
					if (Utils.isDocument(doc))
					{
						if (oldCssClass) {
							element.prev('.workflow-indicator').addBack().removeClass(oldCssClass);
						}
						element.prev('.workflow-indicator').addBack().addClass(doc.publicationStatus);
						oldCssClass = doc.publicationStatus;


						if (Utils.hasCorrection(doc)) {
							scope.data.action = 'correction';
						}
						else {
							scope.data.action = null;
							angular.forEach(['requestValidation', 'contentValidation', 'publicationValidation', 'freeze', 'unfreeze'], function (action) {
								if (doc.isActionAvailable(action)) {
									scope.data.action = action;
								}
							});
							if (scope.data.action === null && attrs.standalone === 'true') {
								$location.path(doc.url());
								return;
							}
						}
						REST.ensureLoaded(doc).then(function (doc)
						{
							if (Utils.hasCorrection(doc)) {
								updateCorrection(doc);
							}

							scope.data.startPublication = doc.startPublication;
							scope.data.endPublication = doc.endPublication;
						});
					}
				}, true);


				scope.submit = function () {
					// When validating the publication, we need to save the 'startPublication' and 'endPublication'
					// properties on the Document before executing the workflow task.
					if (scope.data.action === 'publicationValidation' && publicationDatesChanged()) {
						scope.document.startPublication = scope.data.startPublication;
						scope.document.endPublication = scope.data.endPublication;
						REST.save(scope.document, ['startPublication', 'endPublication']).then(function (updated) {
							angular.extend(scope.document, updated);
							doSubmit();
						});
					}
					else {
						doSubmit();
					}
				};


				scope.closeWorkflow = function () {
					scope.onClose();
				};


				scope.runDirectPublication = function ()
				{
					freezeUI();
					var defer = $q.defer();
					lastUpdatedDoc = null;

					if (scope.document.META$.actions && scope.document.META$.actions.hasOwnProperty('directPublication'))
					{
						REST.call(scope.document.META$.actions['directPublication'].href).then(function (task)
						{
							angular.extend(scope.document, REST.transformObjectToChangeDocument(task.properties.document));
							lastUpdatedDoc = scope.document;
							unfreezeUI();
							defer.resolve();
						});
					}

					return defer.promise;
				};


				//
				// Corrections
				//

				scope.correctionData = {};


				function updateCorrection (doc)
				{
					scope.correctionData.correctionInfo = angular.copy(doc.META$.correction);
					scope.correctionData.diff = [];
					scope.correctionData.advancedDiffs = true;

					scope.correctionData.params = {
						'applyCorrectionWhen' : scope.correctionData.correctionInfo.publicationDate ? 'planned' : 'now',
						'plannedCorrectionDate' : scope.correctionData.correctionInfo.publicationDate
					};

					scope.correctionData.diff.length = 0;
					if (scope.correctionData.correctionInfo) {
						angular.forEach(scope.correctionData.correctionInfo.propertiesNames, function (property) {
							scope.correctionData.diff.push({
								'id'       : property,
								'current'  : doc[property],
								'original' : scope.correctionData.correctionInfo.original[property]
							});
						});
					}
				}


				scope.correctionData.reject = false;


				scope.correctionData.deleteCorrection = function () {
					if (!window.confirm(i18n.trans('m.rbs.admin.adminjs.correction_confirm_delete'))) {
						return;
					}

					REST.modelInfo(scope.document.model).then(function (modelInfo)
					{
						var copy = angular.copy(scope.document);
						if (Utils.removeCorrection(copy, null))
						{
							angular.forEach(copy, function (value, property) {
								if (! modelInfo.properties.hasOwnProperty(property) || ! modelInfo.properties[property].localized) {
									delete copy[property];
								}
							});
							copy.id = scope.document.id;
							copy.model = scope.document.model;
							copy.LCID = scope.document.LCID;
							copy.refLCID = scope.document.refLCID;

							REST.save(copy).then(function (updated) {
								delete scope.document.META$.correction;
								angular.extend(scope.document, updated);
								$location.path(scope.document.url());
							});
						}
					});
				};

				scope.correctionData.canChooseDate = function () {
					if (! scope.correctionData.correctionInfo) {
						return false;
					}
					var cs = scope.correctionData.correctionInfo.status;
					return scope.correctionData.diff.length > 0 && (cs === 'DRAFT' || cs === 'VALIDATION' || cs === 'VALIDCONTENT');
				};


				scope.correctionData.requestValidation = function () {
					accept('requestValidation');
				};

				scope.correctionData.contentValidation = function () {
					accept('contentValidation');
				};

				scope.correctionData.rejectContentValidation = function (message) {
					reject('contentValidation', message);
				};

				scope.correctionData.publicationValidation = function () {
					accept('publicationValidation');
				};

			}
		};

	}]);

})();