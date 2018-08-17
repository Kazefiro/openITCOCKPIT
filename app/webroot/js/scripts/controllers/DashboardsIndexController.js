angular.module('openITCOCKPIT')
    .controller('DashboardsIndexController', function($scope, $http, $timeout){

        /** public vars **/
        $scope.init = true;
        $scope.activeTab = null;
        $scope.availableWidgets = [];
        $scope.fullscreen = false;
        $scope.errors = {};
        $scope.viewTabRotateInterval = 0;
        $scope.intervalText = 'disabled';

        $scope.gridsterOpts = {
            minRows: 2, // the minimum height of the grid, in rows
            maxRows: 100,
            columns: 12, // the width of the grid, in columns
            colWidth: 'auto', // can be an integer or 'auto'.  'auto' uses the pixel width of the element divided by 'columns'
            //rowHeight: 'match', // can be an integer or 'match'.  Match uses the colWidth, giving you square widgets.
            rowHeight: 25,
            margins: [10, 10], // the pixel distance between each widget
            defaultSizeX: 2, // the default width of a gridster item, if not specifed
            defaultSizeY: 1, // the default height of a gridster item, if not specified
            mobileBreakPoint: 600, // if the screen is not wider that this, remove the grid layout and stack the items
            resizable: {
                enabled: true,
                start: function(event, uiWidget, $element){
                }, // optional callback fired when resize is started,
                resize: function(event, uiWidget, $element){
                }, // optional callback fired when item is resized,
                stop: function(event, uiWidget, $element){
                } // optional callback fired when item is finished resizing
            },
            draggable: {
                enabled: true, // whether dragging items is supported
                handle: '.ui-sortable-handle', // optional selector for resize handle
                start: function(event, uiWidget, $element){
                }, // optional callback fired when drag is started,
                drag: function(event, uiWidget, $element){
                }, // optional callback fired when item is moved,
                stop: function(event, uiWidget, $element){
                } // optional callback fired when item is finished dragging
            }
        };


        /** private vars **/
        var tabSortCreated = false;
        var intervalId = null;
        var disableWatch = false;
        var watchTimeout = null;

        var genericError = function(){
            new Noty({
                theme: 'metroui',
                type: 'error',
                text: 'Error while saving data',
                timeout: 3500
            }).show();
        };

        var genericSuccess = function(){
            new Noty({
                theme: 'metroui',
                type: 'success',
                text: 'Data saved successfully',
                timeout: 3500
            }).show();
        };

        $scope.load = function(){
            $http.get("/dashboards/index.json", {
                params: {
                    'angular': true
                }
            }).then(function(result){

                $scope.tabs = result.data.tabs;
                if($scope.activeTab === null){
                    $scope.activeTab = $scope.tabs[0].id;
                }

                $scope.viewTabRotateInterval = result.data.tabRotationInterval;
                updateInterval();

                $scope.availableWidgets = result.data.widgets;
                createTabSort();

                $scope.loadTabContent($scope.activeTab);

                $scope.init = false;
            });
        };

        $scope.loadTabContent = function(tabId){
            disableWatch = true;
            $http.get("/dashboards/getWidgetsForTab/" + tabId + ".json", {
                params: {
                    'angular': true
                }
            }).then(function(result){
                $scope.activeTab = tabId;

                var widgets = [];
                for(var i in result.data.widgets.Widget){
                    widgets.push({
                        sizeX: parseInt(result.data.widgets.Widget[i].width, 10),
                        sizeY: parseInt(result.data.widgets.Widget[i].height, 10),
                        col: parseInt(result.data.widgets.Widget[i].col, 10),
                        row: parseInt(result.data.widgets.Widget[i].row, 10),

                        id: parseInt(result.data.widgets.Widget[i].id, 10),
                        icon: result.data.widgets.Widget[i].icon,
                        title: result.data.widgets.Widget[i].title,
                        directive: result.data.widgets.Widget[i].directive
                    });
                }

                $scope.activeWidgets = widgets;

                //Disable watch for some time to give angular time to render the template
                //Will avoid a saveGrid method call in load (or tab switch)
                setTimeout(function(){
                    disableWatch = false;
                }, 500);
            });
        };


        $scope.saveGrid = function(){
            $scope.checkDashboardLock();

            var postData = [];
            for(var i in $scope.activeWidgets){
                postData.push({
                    Widget: {
                        id: $scope.activeWidgets[i].id,
                        dashboard_tab_id: $scope.activeTab,
                        row: $scope.activeWidgets[i].row,
                        col: $scope.activeWidgets[i].col,
                        width: $scope.activeWidgets[i].sizeX,
                        height: $scope.activeWidgets[i].sizeY
                    }
                });
            }

            $http.post("/dashboards/saveGrid/.json?angular=true", postData).then(
                function(result){
                    genericSuccess();
                    return true;
                }, function errorCallback(result){
                    genericError();
                });
        };

        $scope.addWidgetToTab = function(typeId){
            postData = {
                Widget: {
                    dashboard_tab_id: $scope.activeTab,
                    typeId: typeId
                }
            };
            $http.post("/dashboards/addWidgetToTab/.json?angular=true", postData).then(
                function(result){
                    $scope.activeWidgets.push({
                        sizeX: parseInt(result.data.widget.Widget.width, 10),
                        sizeY: parseInt(result.data.widget.Widget.height, 10),
                        col: parseInt(result.data.widget.Widget.col, 10),
                        row: parseInt(result.data.widget.Widget.row, 10),

                        id: parseInt(result.data.widget.Widget.id, 10),
                        icon: result.data.widget.Widget.icon,
                        title: result.data.widget.Widget.title,
                        directive: result.data.widget.Widget.directive
                    });
                    return true;
                }, function errorCallback(result){
                    genericError();
                });
        };

        $scope.removeWidgetFromTab = function(id){
            postData = {
                Widget: {
                    id: id,
                    dashboard_tab_id: $scope.activeTab
                }
            };


            $http.post("/dashboards/removeWidgetFromTab/.json?angular=true", postData).then(
                function(result){
                    var currentWidgets = [];
                    for(var i in $scope.activeWidgets){
                        if($scope.activeWidgets[i].id == id){
                            $scope.activeWidgets.splice(i, 1);

                            //We are done here
                            break;
                        }
                    }
                }, function errorCallback(result){
                    genericError();
                });
        };

        $scope.refresh = function(){
            $scope.load();
        };

        $scope.toggleFullscreenMode = function(){
            var elem = document.getElementById('widget-container');
            if($scope.fullscreen === true){
                $scope.fullscreen = false;
                if(document.exitFullscreen){
                    document.exitFullscreen();
                }else if(document.webkitExitFullscreen){
                    document.webkitExitFullscreen();
                }else if(document.mozCancelFullScreen){
                    document.mozCancelFullScreen();
                }else if(document.msExitFullscreen){
                    document.msExitFullscreen();
                }
            }else{
                if(elem.requestFullscreen){
                    elem.requestFullscreen();
                }else if(elem.mozRequestFullScreen){
                    elem.mozRequestFullScreen();
                }else if(elem.webkitRequestFullscreen){
                    elem.webkitRequestFullscreen();
                }else if(elem.msRequestFullscreen){
                    elem.msRequestFullscreen();
                }

                $('#widget-container').css({
                    'width': $(window).width(),
                    'height': $(window).height()
                });

                $scope.fullscreen = true;
            }
        };

        $scope.addNewTab = function(){
            $http.post("/dashboards/addNewTab.json?angular=true",
                {
                    DashboardTab: {
                        name: $scope.newTabName
                    }
                }
            ).then(function(result){
                genericSuccess();

                $scope.activeTab = parseInt(result.data.DashboardTab.DashboardTab.id, 10);
                $scope.load();
                $('#addNewTabModal').modal('hide');
            }, function errorCallback(result){
                $scope.errors = result.data.error;
                genericError();
            });
        };

        $scope.saveTabRotateInterval = function(){
            $http.post("/dashboards/saveTabRotateInterval.json?angular=true",
                {
                    User: {
                        dashboard_tab_rotation: $scope.viewTabRotateInterval
                    }
                }
            ).then(function(result){
                genericSuccess();
                updateInterval();

            }, function errorCallback(result){
                $scope.errors = result.data.error;
                genericError();
            });
        };

        if(document.addEventListener){
            document.addEventListener('webkitfullscreenchange', fullscreenExitHandler, false);
            document.addEventListener('mozfullscreenchange', fullscreenExitHandler, false);
            document.addEventListener('fullscreenchange', fullscreenExitHandler, false);
            document.addEventListener('MSFullscreenChange', fullscreenExitHandler, false);
        }

        function fullscreenExitHandler(){
            if(document.webkitIsFullScreen === false || document.mozFullScreen === false || document.msFullscreenElement === false){
                $scope.fullscreen = false;
                $('#widget-container').css({
                    'width': '100%',
                    'height': '100%'
                });
            }
        }

        var createTabSort = function(){
            if(tabSortCreated === true){
                return;
            }

            tabSortCreated = true;
            $('.nav-tabs').sortable({
                update: function(){
                    var $tabbar = $(this);
                    var $tabs = $tabbar.children();
                    var tabIdsOrdered = [];
                    $tabs.each(function(key, tab){
                        var $tab = $(tab);
                        var tabId = parseInt($tab.data('tab-id'), 10);
                        tabIdsOrdered.push(tabId);
                    });
                    $http.post("/dashboards/saveTabOrder.json?angular=true",
                        {
                            order: tabIdsOrdered
                        }
                    ).then(function(result){
                        genericSuccess();
                    }, function errorCallback(result){
                        genericError();
                    });
                },
                placeholder: 'tabTargetDestination'
            });

        };

        var rotateTab = function(){
            console.log('Implement me!');
        };

        var updateInterval = function(){
            if(intervalId !== null){
                $interval.cancel(intervalId);
            }

            if($scope.viewTabRotateInterval > 0){
                intervalId = $interval(rotateTab, ($scope.viewTabRotateInterval * 1000));
            }
        };

        $scope.checkDashboardLock = function(){

        };

        /** On Load stuff **/
        $scope.$watch('viewTabRotateInterval', function(){
            if($scope.init){
                return;
            }

            if($scope.viewTabRotateInterval === 0){
                $scope.intervalText = 'disabled';
            }else{
                var min = parseInt($scope.viewTabRotateInterval / 60, 10);
                var sec = parseInt($scope.viewTabRotateInterval % 60, 10);
                if(min > 0){
                    $scope.intervalText = min + ' minutes, ' + sec + ' seconds';
                    return;
                }
                $scope.intervalText = sec + ' seconds';
            }
        });

        $scope.$watch('activeWidgets', function(){
            console.log(disableWatch);
            if($scope.init === true || disableWatch === true){
                return;
            }

            if(watchTimeout !== null){
                $timeout.cancel(watchTimeout);
            }

            watchTimeout = $timeout(function(){
                $scope.saveGrid();
                /*
                for(var i in $scope.activeWidgets){
                    var col = $scope.activeWidgets[i].col;
                    var row = $scope.activeWidgets[i].row;

                    console.log('<col>'+col+'</col>     <row>'+row+'</row>');
                }
                */
            }, 1500);
        }, true);

        $scope.load();
    });