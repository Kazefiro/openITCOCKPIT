angular.module('openITCOCKPIT')
    .controller('NotificationsHostNotificationController', function($scope, $http, $rootScope, $httpParamSerializer, SortService, QueryStringService, $stateParams) {

        SortService.setSort(QueryStringService.getValue('sort', 'NotificationHosts.start_time'));
        SortService.setDirection(QueryStringService.getValue('direction', 'desc'));
        $scope.currentPage = 1;

        $scope.id = $stateParams.id;

        $scope.useScroll = true;

        var now = new Date();

        /*** Filter Settings ***/
        var defaultFilter = function() {
            $scope.filter = {
                NotificationHosts: {
                    state: {
                        recovery: false,
                        down: false,
                        unreachable: false
                    },
                    state_types: {
                        soft: false,
                        hard: false
                    },
                    output: '',
                    author: ''
                },
                from: date('d.m.Y H:i', now.getTime() / 1000 - (3600 * 24 * 30)),
                to: date('d.m.Y H:i', now.getTime() / 1000 + (3600 * 24 * 30 * 2))
            };
        };
        /*** Filter end ***/

        $scope.init = true;
        $scope.showFilter = false;

        $scope.hostBrowserMenuConfig = {
            autoload: true,
            hostId: $scope.id,
            includeHoststatus: true
        };

        $scope.load = function() {

            $http.get("/notifications/hostNotification/" + $scope.id + ".json", {
                params: {
                    'angular': true,
                    'scroll': $scope.useScroll,
                    'sort': SortService.getSort(),
                    'page': $scope.currentPage,
                    'direction': SortService.getDirection(),
                    'filter[NotificationHosts.output]': $scope.filter.NotificationHosts.output,
                    'filter[NotificationHosts.state][]': $rootScope.currentStateForApi($scope.filter.NotificationHosts.state),
                    'filter[from]': $scope.filter.from,
                    'filter[to]': $scope.filter.to
                }
            }).then(function(result) {
                $scope.notifications = result.data.all_notifications;
                $scope.paging = result.data.paging;
                $scope.scroll = result.data.scroll;

                $scope.init = false;
            });
        };

        $scope.triggerFilter = function() {
            $scope.showFilter = !$scope.showFilter === true;
        };

        $scope.resetFilter = function() {
            defaultFilter();
        };


        $scope.changepage = function(page) {
            if (page !== $scope.currentPage) {
                $scope.currentPage = page;
                $scope.load();
            }
        };

        $scope.changeMode = function(val) {
            $scope.useScroll = val;
            $scope.load();
        };

        //Fire on page load
        defaultFilter();
        SortService.setCallback($scope.load);

        $scope.$watch('filter', function() {
            $scope.currentPage = 1;
            $scope.load();
        }, true);

    });