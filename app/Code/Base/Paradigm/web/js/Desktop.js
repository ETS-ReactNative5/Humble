//------------------------------------------------------------------------------
//Title: Easy Desktop
//
//Part of the Cloud-IT project
//
//All licensing and copyrights are held by Cloud-IT.  Use is by permission from
//  Rick Myers [rick@enicity.com]
//
//All other rights are reserved.
//Copyright 2014, Cloud-IT.com
//------------------------------------------------------------------------------
function WindowAttributeClass(id,x,y,w,h) {
    return this;  //Short circuit it for now
    this.id     = id;
    this.left   = x+"px";
    this.top    = y+"px";
    this.width  = w+"px";
    this.height = h+"px";
    this.save   = function () {
         if (!virtualWindows.application.user)
            return false;   //no userid
        (new EasyAjax("/desktop/attributes/save")).add("dimensions",JSON.stringify(this)).then(function () {}).post();
    };
    return  this;
}
//------------------------------------------------------------------------------
//
//------------------------------------------------------------------------------
function IconAttributeClass(id,x,y) {
    return this;  //Short circuit it for now
    this.id     = id;
    this.left   = x+"px";
    this.top    = y+"px";
    this.save   = function () {
         if (!virtualWindows.application.user)
            return false;   //no userid
        (new EasyAjax("/desktop/attributes/save")).add("dimensions",JSON.stringify(this)).then(function () {}).post();
    };
    return  this;
}

//------------------------------------------------------------------------------
//Each icon gets one
//------------------------------------------------------------------------------
function DesktopIcon(icon,refId) {
    var id              = icon.id;
    this.window         = null;
    this.referenceId    = (refId) ? refId : false;  //legacy backwards compatibility
    this.data           = icon;
    this.ref            = $E("cloud-it-"+id+"-icon");
    this.text           = $E("cloud-it-"+id+"-icon-text");
    this._text          = function (text) {
        this.text.innerHTML = text;
        return this;
    };
    this._open      = function (evt) {
        evt = (evt) ? evt : ((window.event) ? event : null);
        this.window = Desktop.window.list[id];
        // 0=closed, 1=minimized, 2=open, 3=maximized
        switch (this.window.state) {
            case    0   :   if (this.window._open) {
                                this.window._open(evt);
                                this.window._tofront(evt);
                            }
                            break;
            case    1   :   this.window._reopen(evt);
                            this.window.frame.fadeIn();
                            break;
            case    2   :   this.window._tofront(evt);
                            break;
            case    3   :   this.window._tofront(evt);
                            break;
            default     :   console.log('unknown state '+win.state);
                            break;
        }
        return this;
    };
}

//------------------------------------------------------------------------------
//Each window gets one
//
//In general, the underscore "_" methods are the default behaviors, and they
// give a check to see if there are methods similarly named but without the
// underscore, and then call those.  This way a person can add a custom handler
// for that event/method for the window in particular.
//------------------------------------------------------------------------------
function DesktopWindow(icon,refId) {
    this.id             = icon.id;
    this.icon           = icon;
    this.referenceId    = (refId) ? refId : false;  //legacy
    this.nav            = false;
    this.header         = false;
    this.footer         = false;
    this.left           = 0;
    this.body           = false;
    this.ajax           = null;
    this.frame          = $E("cloud-it-window-"+this.id+"-frame");
    this.title          = $E("cloud-it-window-"+this.id+"-title");
    this.titleText      = this.title.innerHTML;
    this.content        = $E("cloud-it-window-"+this.id+"-content");
    this.static         = false;  //if true, the window handle won't be returned to the semaphore
    this.content.height = (function (content) {
        return function () {
            return content.offsetHeight;
        };
    })(this.content);
    this.content.width = (function (content) {
        return function () {
            return content.offsetWidth;
        };
    })(this.content);
    this.bar            = $E("cloud-it-window-"+this.id+"-titlebar");
    this.maximizeIcon   = $E("cloud-it-window-"+this.id+"-maximize");
    this.minimizeIcon   = $E("cloud-it-window-"+this.id+"-minimize");
    this.closeIcon      = $E("cloud-it-window-"+this.id+"-close");
    this.splashScreen   = $E("cloud-it-window-"+this.id+"-splash");
    this.namespace      = icon.namespace;     //I am a window of what application
    this.appid          = 0;        //numerical instance of an app
    this.state          = 0;        //0 - closed, 1 - minimized, 2 - open, 3 - maximized
    this.lastState      = 0;
    this.original       = {
        width:  0,
        height: 0
    }
    this.premaxed       = {
        x:      0,
        y:      0,
        w:      0,
        h:      0
    };
    this.saved          = {
        x:      0,
        y:      0,
        w:      0,
        h:      0
    };
    this._scroll    = function (scroll) {
        this.content.style.overflow = (scroll) ? 'auto' : 'hidden';
        return this;
    }
    this._title     = function (title) {
        if (title) {
            var project = ParadigmConfig.desktop.window.name ? ParadigmConfig.desktop.window.name : ParadigmConfig.desktop.default.window.name;
            this.title.innerHTML = this.titleText = title + ' | '+project;
            return this;
        } else {
            return this.titleText;
        }
    };
    this._resize     = function (evt) {
        this.content.style.height = (this.frame.offsetHeight - this.bar.offsetHeight - 4)+"px";
        if (this.resize) {
            this.resize(this);
        }
        //$(window).resize();  //this can fire off a lot of other things
        return this;
    };
    this._open       = function (evt){
        this.lastState = this.state;
        this.state = 2;
        this.frame.style.display = "block";
        if (typeof(evt) === "string") {
            $(this.content).html(evt);
        }
        this._resize();
        this._tofront();
        var me = this;
        if (this.splash) {
            this.splashScreen.style.zIndex = '9000';
            this.splashScreen.fadeIn();
            var splash = (typeof(this.splash) === "function") ? this.splash() : this.splash;
            (new EasyAjax(splash)).then(function (response) {
               $(me.splashScreen).html(response);
               var tt = (function (screen) {
                    return function () {
                        screen.fadeOut(screen.remove);
                    };
               })(me.splashScreen);
               window.setTimeout(tt,2000);
            }).post();
        }
        if (this.open) {
            this.open(this);
        }
        return this;
    };
    this._static     = function (boolean) {
        this.static = boolean;
        return this;
    };
    this._close      = function (evt) {
        if (this.frame) {
            var doit = true;
            if (this.close) {
                doit = this.close(this);
            }
            if (doit!==false) {
                this.lastState = this.state;
                this.state = 0;
                this.frame.style.display = "none";
                this.content.innerHTML = '';
            }
        }
        this.resize = false;
        if (!this.static) {
            Desktop.semaphore.checkin(this.id);
        }
        return this;
    };
    this._tofront   = function (evt) {
        Desktop.window.reset();
        Desktop.window.set(this);
        return this;
    };
    this._front = this._tofront;
    this._reopen     = function () {
        this.frame.style.left = this.left + "px";
        $(this.frame).show();
        return this;
    };
    this._restore = this._reopen;
    this._maximize   = function (evt) {
        this.lastState = this.state;
        if (this.state === 3) {
            this.frame.style.top    = this.premaxed.y + "px";
            this.frame.style.left   = this.premaxed.x + "px";
            this.frame.style.width  = this.premaxed.w + "px";
            this.frame.style.height = this.premaxed.h + "px";
            this.state = 2;
        } else {
            this.premaxed.x = this.frame.offsetLeft;
            this.premaxed.y = this.frame.offsetTop;
            this.premaxed.w = this.frame.offsetWidth;
            this.premaxed.h = this.frame.offsetHeight;
            this.frame.style.top = '5px';
            this.frame.style.left = '5px';
            this.frame.style.width = (Desktop.width()-32)+"px";
            this.frame.style.height = (Desktop.height()-32)+"px";
            this.state = 3;
        }
        this._resize(this);
        return this;
    };
    this._minimize  = function (evt) {
        var win     = this.win;
        this.left   = this.frame.offsetLeft;
        this.lastState = this.state;
        this.state  = 1;
        $(this.frame).fadeOut(function () {
            this.style.left = (-1 * (this.offsetLeft + this.offsetWidth)) + "px";
        });
        if (this.minimize) {
            this.minimize(this);
        }
        return this;
    };
    this.set    = function (html) {
        $(this.content).html(html);
        return this;
    };
    this._content    = this.set;
    this.reload = function () {
        if (this.open) {
            this.open(this);
        }
        return this;
    };
    this.moveTo = function (x,y) {
        this.frame.style.left = x + "px";
        this.frame.style.top  = y + "px";
        return this;
    }
    this.resizeTo = function (w,h) {
        console.log(w+','+h);
        if (w) {
            this.frame.style.width  = w + "px";
        }
        if (h) {
            this.frame.style.height = h + "px";
        }
        return this._resize(this);
    }
    this._reset = function () {
        this.resize = null;
        this.close  = null;
        this.open   = null;
        this._title('');
        this._scroll(false);
        return this._static(false);
    }
    this.dock   = function (where) {
        var w = window.innerWidth;
        var h = window.innerHeight;
        var hw = Math.round(w/2)-2;
        var hh = Math.round(h/2)-2;
        switch (where) {
            case 'L' :
                this.moveTo(0,0).resizeTo(hw,h);
                break;
            case 'TR' :
                this.resizeTo(hw,hh).moveTo(hw+2,0);
                break;
            case 'TL' :
                this.resizeTo(hw,hh).moveTo(0,0);
                break;
            case 'BL' :
                this.resizeTo(hw,hh).moveTo(0,hh+2);
                break;
            case 'BR' :
                this.resizeTo(hw,hh).moveTo(hw+2,hh+2);
                break;
            case 'R' :
                this.resizeTo(hw,h).moveTo(hw+2,0);
                break;
            default  :
                break;
        }
    }
    return this;
}
DesktopWindow.corner = function () {
    var positioner = '<div class="paradigm-window-layout-container"><div onclick="Desktop.window.align(this,\'L\')" class="paradigm-window-left-layout"></div><div class="paradigm-window-center-layout"><div onclick="Desktop.window.align(this,\'TL\')" class="paradigm-window-top-left-layout"></div><div onclick="Desktop.window.align(this,\'TR\')" class="paradigm-window-top-right-layout"></div><div onclick="Desktop.window.align(this,\'BL\')" class="paradigm-window-bottom-left-layout"></div><div onclick="Desktop.window.align(this,\'BR\')" class="paradigm-window-bottom-right-layout"></div></div><div onclick="Desktop.window.align(this,\'R\')" class="paradigm-window-right-layout"></div></div>'
    var icon       = '<img src="'+ParadigmConfig.desktop.window.icon+'" style="margin-right: 8px; margin-left:4px;  position: relative; top: -3px" height="25" align="middle" /> '
    return (ParadigmConfig.desktop.window.icon) ?  icon : positioner;

}
//------------------------------------------------------------------------------
//
//------------------------------------------------------------------------------
var Desktop = {
    /*  --------------------------------------------------------------------
     *  A semaphore is a style of programming involving the management of a
     *    fixed number of resources, in this case, the dynamic windows
     *  --------------------------------------------------------------------*/
    semaphore: {
        list: [],
        lastElement: null,
        init: function () {
            var ctr=0;
            for (var i in Desktop.window.list) {
                Desktop.window.list[i].open = (function (id) {
                    return function () {
                        Desktop.lastElement = false;  //transfers focus from the canvas to the open window
                    }
                })(i);
                Desktop.semaphore.list[+ctr] = {
                    id: i,
                    available: true
                }
                ctr++;
            }
        },
        checkout: function (win) {
            var max     = Desktop.semaphore.list.length;
            var ctr     = 0;
            var found   = false;
            while (!found && (ctr<max)) {
                found = Desktop.semaphore.list[ctr].available;
                if (!found) {
                    ctr++;
                } else {
                    Desktop.semaphore.list[ctr].available = false;
                }
            }
            return (found) ? ((win) ? Desktop.window.list[Desktop.semaphore.list[ctr].id] : Desktop.semaphore.list[ctr].id) : false;
        },
        checkin: function (id) {
            var max     = Desktop.semaphore.list.length;
            var ctr     = 0;
            var found   = false;
            while (!found && (ctr<max)) {
                if (Desktop.semaphore.list[ctr].id === id) {
                    found = true;
                    Desktop.semaphore.list[ctr].available = true;
                    if (Desktop.window.list[id].state) {
                        Desktop.window.list[id]._close();
                    }
                }
                ctr=ctr+1;
            }
            return found;
        }
    },
    minimized: {
        windows: {
            list: {},
            set: function (windowId) {
                Desktop.minimized.windows.list[windowId] = Desktop.window.list[windowId];
                if (Desktop.minimized.windows.render) {
                   Desktop.minimized.windows.render(Desktop.minimized.windows.list);
                }
            },
            restore: function (windowId) {
                Desktop.minimized.windows.list[windowId]._restore();
                delete Desktop.minimized.windows.list[windowId];
                if (Desktop.minimized.windows.render) {
                   Desktop.minimized.windows.render(Desktop.minimized.windows.list);
                }
            },
            render: false,
            renderer: function (callback) {
                Desktop.minimized.windows.render = callback;
            }
        }
    },
    logoffOnReload: true,
    refreshing: false,
    polls:  [],
    whoami:  function (node,obj) {
        if (typeof(node) == "string") {
            node = document.getElementById(node);
        }
        var result = null;
        while (node) {
            result = node.getAttribute('desktop_id');
            if (result) {
                return (obj) ? Desktop.window.list[result] : result;
            }
            node = (node.offsetParent) ? node.offsetParent : false;
        }
    },
    reload: function () {
        Desktop.refreshing = true;
        window.location.reload();
    },
    toFront: function (win) {
        if (win._front) {
            win._front();
        }
        return true;
    },
    promptBeforeLeaving: function (evt) {
        var win     = null;
        var status  = true;
        if ((!Desktop.refreshing) && (Desktop.logoffOnReload)) {
            if (virtualWindows.application.user) {
                for (var i in Desktop.window.list) {
                    win = Desktop.window.list[i];
                    if ((win.state === 1) || (win.state === 2) || (win.state === 3)) {
                        status = status && win.close();
                    }
                }
                return ((status) ? "You are about to logoff, is this what you want to do?" : "One or more of the windows you have opened require attention, would you still like leave?");
            }
        }
        Desktop.logoff = true;
    },
    unload: function (evt) {
        for (var i in Desktop.polls) {
            if (Desktop.polls[i].ref) {
                window.clearTimeout(Desktop.polls[i].ref);
            }
        }
    },
    logoff:  function () {
        if (virtualWindows.application.user)	{
            Desktop.logoffOnReload  = false;
            Desktop.refreshing      = true;
            (new EasyAjax("/desktop/actions/logoff")).then(function () {
                window.location.replace("/index.html");
                return true;
            }).post(false);
        }
    },
    templates: {
        icon:   '<div id="cloud-it-&&w_id&&-icon" class="cloud-it-desktop-icon" desktop_id="&&w_id&&">'+
                '<img src="&&image&&" alt="&&text&&" class="cloud-it-desktop-icon-image" /><br />'+
                '<span class="cloud-it-desktop-icon-text" id="cloud-it-&&w_id&&-icon-text">&&text&&</span>'+
                '</div>',
        window: '<div class="cloud-it-desktop-window-frame" id="cloud-it-window-&&w_id&&-frame" desktop_id="&&w_id&&" onclick="Desktop.stopPropagation(event)" onmousedown="Desktop.stopPropagation(event)" onmouseover="Desktop.stopPropagation(event)">'+
                '<div id="cloud-it-window-&&w_id&&-splash" style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; display: none"></div>'+
                '<div class="cloud-it-desktop-window-titlebar" id="cloud-it-window-&&w_id&&-titlebar" desktop_id="&&w_id&&">'+
                '<img desktop_id="&&w_id&&" style="cursor: pointer;" id="cloud-it-window-&&w_id&&-close" class="cloud-it-window-close" src="/images/paradigm/desktop/redx.png" alt="Close" title="Close this window" onmousedown="Desktop.stopPropagation(event)" />'+
                '<img desktop_id="&&w_id&&" style="position: relative; top: -2px; cursor: pointer; height: 20px; width: 20px" id="cloud-it-window-&&w_id&&-maximize" class="cloud-it-window-maximize" src="/images/paradigm/desktop/maximize.gif" title="Expand window to fullest" alt="Zoom Page"  />'+
                '<img desktop_id="&&w_id&&" style="position: relative; height: 20px; width: 20px; cursor: pointer;" id="cloud-it-window-&&w_id&&-minimize" class="cloud-it-window-minimize" src="/images/paradigm/desktop/minimize.gif" alt="Hide Window" title="Hide this window"  />'+
                DesktopWindow.corner()+'<span onclick="return false" desktop_id="&&w_id&&" id="cloud-it-window-&&w_id&&-title">&&title&&</span>'+
                '</div>'+
                '<div class="cloud-it-desktop-window-content" id="cloud-it-window-&&w_id&&-content" desktop_id="&&w_id&&" onmousedown="Desktop.stopPropagation(event)" onmouseover="Desktop.stopPropagation(event)">'+
                '</div></div>'

    },
    isMobileDevice: function ()  {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    },
    width:  function () {
        return window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
    },
    height: function () {
        return window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
    },
    isModern: (window.addEventListener ? true : false),
    scrollLeft: function () {
        return (Desktop.isModern) ? window.pageXOffset : Desktop.ref.iebody.scrollLeft;
    },
    scrollTop: function () {
        return (Desktop.isModern) ? window.pageYOffset : Desktop.ref.iebody.scrollTop;
    },
    on: function (obj,event,handler) {
        var result = null;
        if (typeof obj == 'string') {
            obj = document.getElementById(obj);
        }
        if (Desktop.isModern) {
            result = obj.addEventListener(event,handler,false);
        } else {
            result = obj.attachEvent('on'+event,handler);
        }
        return result;
    },
    off: function (obj,event,handler) {
        if (typeof obj == 'string') {
            obj = document.getElementById(obj);
        }
        if (Desktop.isModern) {
            obj.removeEventListener(event,handler,false);
        } else {
            obj.detachEvent('on'+event,handler);
        }
    },
    addPoll:  function (id,interval,handler){
        if (Desktop.polls[id]) {
            window.clearTimeout(Desktop.polls[id]);
        }
        (Desktop.polls[id] = {
            ref: window.setInterval(handler,interval),
            handler: handler,
            interval: interval
        }).handler();
    },
    addTimeout: function (id,interval,handler) {
        //TODO:
        //same as addpoll, but handler must return true to continue the timeout cycle
        if (Desktop.polls[id]) {
            window.clearTimeout(Desktop.polls[id]);
        }
        (Desktop.polls[id] = {
            ref: window.setTimeout(handler,interval),
            handler: handler,
            interval: interval
        }).handler();

    },
    suppressIcons:  function ()	{
        for (var i in Desktop.icon.list) {
            if (Desktop.icon.list[i].ref) {
                $E(Desktop.icon.list[i].ref).style.display = "none";
            }
        }
    },
    showIcons:  function () {
        for (var i in Desktop.icon.list)
            $E(Desktop.icon.list[i].ref).style.display = "none";
    },
    ref: null,
    drag: {
        window: {
            ref: null,
            current: null,
            resize: {
                left:   false,
                top:    false,
                right:  false,
                bottom: false
            },
            scroll: {
                left: 0,
                top: 0
            },
            click: {
                X: 0,
                Y: 0
            },
            start: {
                X: 0,
                Y: 0,
                W: 0,
                H: 0
            },
            delta: {
                X: 0,
                Y: 0
            }
        },
        icon: {
            ref: null,
            current: null,
            scroll: {
                left: 0,
                top: 0
            },
            click: {
                X: 0,
                Y: 0
            },
            start: {
                X: 0,
                Y: 0
            },
            delta: {
                X: 0,
                Y: 0
            }
        }
    },
    elements: null,
    stopPropagation: function (evt) {
        evt = (evt) ? evt : ((window.event) ? event : null);
        (Desktop.isModern) ? evt.stopPropagation() : evt.cancelBubble = true;
        return false;
    },
    id:  function (id) {
        return (+id == id) ? 'cloud-it-app-'+id : id;
    },
    getDesktopId: function (evt) {
        evt = (evt) ? evt : ((window.event) ? event : null);
        var objectId = Desktop.getElementId(evt);
        if (objectId) {
            var it;
            if ($E(objectId)) {
                objectId = (it = $E(objectId).getAttribute("desktop_id")) ? it : objectId;
            }
        }
        return objectId;
    },
    getElementId: function (evt)  {
        var objectId = "";

        if (Desktop.isModern) {
            objectId = evt.target.id ? evt.target.id : (evt.currentTarget.id ? evt.currentTarget.id : null);
        } else {
            if (evt.srcElement.id !== "")
                objectId = evt.srcElement.id;
            else if (evt.srcElement.parentElement.id !== "")
                objectId = evt.srcElement.parentElement.id;
            else if (evt.srcElement.parentElement.parentElement.id !== "")
                objectId = evt.srcElement.parentElement.parentElement.id;
        }
        return objectId;
    },
    init: function (doAfter) {
        Desktop.ref = $E("paradigm-virtual-desktop"); //hardcoded
        Desktop.ref.iebody = (document.documentElement) ? document.documentElement : document.body; //blah for older IE
        Desktop.elements = eval(virtualWindows);
        if (doAfter) {
            doAfter();
        }
    },
    enable: function () {
        Desktop.render().activate().position();
        try {
            if (UseTranparentWindows) {
                virtualWindows.application.windowStyle = 'transparent';
            }
        } catch (ex) {
            console.log(ex);
        }
    },
    render: function () {
        var icon = null;  //refId is for the special case of processing numeric ids... so javascript doesn't go crazy
        for (var i=0; i<Desktop.elements.icons.length; i++) {
            icon = Desktop.elements.icons[i];
            icon.refId = icon.id;
            icon.id = Desktop.id(icon.id); //adjust for numeric ids
            if (icon.image) {
                Desktop.icon.add(icon);
            }
            Desktop.window.add(icon);
        }
        Desktop.ref.innerHTML += Desktop.icon.HTML;
        Desktop.ref.innerHTML += Desktop.window.HTML;

        for (i=0; i<Desktop.elements.icons.length; i++) {
            icon = Desktop.elements.icons[i];
            Desktop.icon.list[icon.id]     = new DesktopIcon(icon,icon.refId);
            Desktop.window.list[icon.id]   = new DesktopWindow(icon,icon.refId);
        }
        return Desktop;
    },
    activate: function () {
        var icon = null; var win = null;
        for (var i=0; i<Desktop.elements.icons.length; i++) {
            icon = Desktop.elements.icons[i];
            if (icon.image) {
                if (icon.handler) {
                    Desktop.window.list[icon.id]._open = icon.handler;
                    Desktop.on(Desktop.icon.list[icon.id].ref,"dblclick", function (evt) { evt = (evt) ? evt : ((window.event) ? event : null); Desktop.window.list[Desktop.getDesktopId(evt)]._open(evt); });
                    Desktop.on(Desktop.icon.list[icon.id].ref,"selectstart", function () { return false; });
                } else {
                    Desktop.window.list[icon.id].open = icon.open ? icon.open : Desktop.window.open;
                    var tt = function (evt) {
                        var icon = Desktop.icon.list[Desktop.getDesktopId(evt)]._open(evt);
                    };
                    Desktop.on(Desktop.icon.list[icon.id].ref,"dblclick", tt);
                }
                Desktop.on(Desktop.icon.list[icon.id].ref,"mousedown", Desktop.icon.drag.start);
            }
            win = Desktop.window.list[icon.id];
            Desktop.on(win.bar,"selectstart",function ()    {   return false;   });
            Desktop.on(win.frame,"selectstart",function ()  {   return false;   });
            Desktop.on(win.frame,"mouseover", Desktop.window.cursor.set);
            Desktop.on(win.frame,"mouseout", Desktop.window.cursor.reset);
            Desktop.on(win.content,"mouseover", Desktop.stopPropagation);
            Desktop.on(win.frame,"mousedown", Desktop.window.drag.start);

            Desktop.window.list[icon.id].close = (icon.close) ? icon.close : Desktop.window.close;
            var tt = function (evt) {
                var win = Desktop.window.list[Desktop.getDesktopId(evt)];
                if (win._close) {
                    win._close(evt);  //do the custom, then do the normal
                }
            };
            Desktop.on(win.closeIcon,"click",tt);
            Desktop.on(win.closeIcon,"mouseover",Desktop.stopPropagation);
            Desktop.window.list[icon.id].maximize = (icon.maximize) ? icon.maximize : Desktop.window.maximize;
            var tt = function (evt) {
                var win = Desktop.window.list[Desktop.getDesktopId(evt)];
                if (win._maximize(evt) && win.maximize) {
                    win.maximize(evt);
                }
            };
            Desktop.on(win.bar,"dblclick",tt);
            Desktop.on(win.bar,"selectstart",function () {return false;});
            Desktop.on(win.maximizeIcon,"click",tt);
            Desktop.on(win.maximizeIcon,"mouseover",Desktop.stopPropagation);
            Desktop.window.list[icon.id].maximize = (icon.maximize) ? icon.maximize : Desktop.window.maximize;
            var tt = function (evt) {
                var win = Desktop.window.list[Desktop.getDesktopId(evt)];
                if (win._minimize(evt) && win.minimize) {
                    win.minimize(evt);
                }
            };
            Desktop.on(win.minimizeIcon,"click",tt);
            Desktop.on(win.minimizeIcon,"mouseover",Desktop.stopPropagation);
            Desktop.window.list[icon.id].resize = icon.resize ? icon.resize : Desktop.window.resize;
            Desktop.window.list[icon.id].splash = (icon.splash) ? icon.splash : false;
        }
        return Desktop;
    },
    position: function () {
        var attr = '';

        Desktop.icon.position();
        Desktop.window.position();
        for (var i in Desktop.elements.attributes) {
            attr = Desktop.elements.attributes[i];
            if ($E(attr.id)) {
                $E(attr.id).style[attr.name] = attr.value;
            }
        }
        return true;
    },
    icon: {
        list:       [],
        ids:        [],
        HTML:       "",
        add: function (icon) {
            Desktop.icon.HTML += Desktop.templates.icon.replace(/&&w_id&&/g,icon.id).replace(/&&text&&/g,icon.text).replace(/&&image&&/g,icon.image);;
        },
        reset: function () {
            for (var i in Desktop.icon.list) {
                if (Desktop.icon.list[i].ref) {
                    Desktop.icon.list[i].ref.style.zIndex = 3;
                }
            }
        },
        position: function () {
            var xSpacer		= 80;
            var ySpacer		= 87;
            var startFrom	= 80;
            var currentY	= startFrom;
            var currentX	= 30;
            for (var i=0; i<Desktop.elements.icons.length; i++) {
                var icon = Desktop.elements.icons[i];
                if (icon.image) {
                    var easyIcon		= $E("cloud-it-"+icon.id+"-icon");  //first place the icon
                    if (isNaN(parseInt(easyIcon.style.top)))  {
                        easyIcon.style.position = "absolute";
                        easyIcon.style.top      = currentY + "px";
                        easyIcon.style.left     = currentX + "px";
                        currentY                += ySpacer;
                        if (currentY >= ((screen.availHeight - ySpacer) - 100)) {
                            currentX += xSpacer;
                            currentY = startFrom;
                        }
                    }
                }
                if (icon.autoOpen === "Y") {
                    Desktop.icon.list[icon.id]._open();
                }
            }
        },
        drag: {
            start: function (evt) {
                evt = (evt) ? evt : ((window.event) ? event : null);
                Desktop.drag.icon.current   = Desktop.getElementId(evt);
                Desktop.drag.icon.ref       = $E(Desktop.drag.icon.current);
                Desktop.icon.reset();
                Desktop.drag.icon.ref.style.zIndex = 9;

                Desktop.drag.icon.scroll.left = Desktop.scrollLeft();
                Desktop.drag.icon.scroll.top  = Desktop.scrollTop();
                if (Desktop.isModern){
                    evt.preventDefault();
                }
                Desktop.drag.icon.click.X	= +Desktop.drag.icon.scroll.left + evt.clientX;
                Desktop.drag.icon.click.Y	= +Desktop.drag.icon.scroll.top  + evt.clientY;
                Desktop.drag.icon.start.X   = +Desktop.drag.icon.ref.offsetLeft;
                Desktop.drag.icon.start.Y   = +Desktop.drag.icon.ref.offsetTop;
                Desktop.drag.icon.delta.X   = Desktop.drag.icon.click.X - Desktop.drag.icon.start.X;
                Desktop.drag.icon.delta.Y   = Desktop.drag.icon.click.Y - Desktop.drag.icon.start.Y;
                Desktop.off(Desktop.drag.icon.ref,"mousedown",Desktop.icon.drag.start);
                Desktop.on(document,'mousemove',Desktop.icon.drag.active);
                Desktop.on(document,'mouseup',Desktop.icon.drag.stop);
                return false;
            },
            active: function (evt) {
                evt = (evt) ? evt : ((window.event) ? event : null);

                if (Desktop.drag.icon.ref )	{
                    var scrollLeft=0;
                    var scrollTop = 0;
                    if (Desktop.isModern) {
                        scrollLeft  = window.pageXOffset;
                        scrollTop	= window.pageYOffset;
                    } else	{
                        scrollLeft  = Desktop.ref.iebody.scrollLeft;
                        scrollTop	= Desktop.ref.iebody.scrollTop;
                    }
                    var newPosX		= (+scrollLeft + evt.clientX - Desktop.drag.icon.delta.X);
                    var newPosY		= (+scrollTop  + evt.clientY - Desktop.drag.icon.delta.Y);
                    if (newPosX <= 1) newPosX = 1;
                    if (newPosY <= 1) newPosY = 1;
                    Desktop.drag.icon.ref.style.left	= newPosX+"px";
                    Desktop.drag.icon.ref.style.top		= newPosY+"px";
                }
                return false;
            },
            stop: function (evt) {
                evt = (evt) ? evt : ((window.event) ? event : null);
                var icon = Desktop.drag.icon;
                if ((icon.ref.offsetLeft !== icon.start.X) || (icon.ref.offsetTop !== icon.start.Y)) {
                    (new IconAttributeClass(icon.current,icon.ref.offsetLeft,icon.ref.offsetTop)).save();
                }

                Desktop.icon.reset();
                Desktop.on(icon.ref,"mousedown",Desktop.icon.drag.start);
                Desktop.off(document,'mousemove',Desktop.icon.drag.active);
                Desktop.off(document,'mouseup',Desktop.icon.drag.stop);
                return false;
            }
        }
    },
    window: {
        list:       [],
        HTML:       "",
        align: function (win,where) {
            var desktop_id = null;
            while (win.parentNode && !desktop_id) {
                desktop_id = (win.getAttribute('desktop_id'));
                win = win.parentNode;
            }
            if (desktop_id) {
                win = Desktop.window.list[desktop_id];
                win.dock(where);
             }
        },
        add: function (icon) {
            Desktop.window.HTML += Desktop.templates.window.replace(/&&w_id&&/g,icon.id).replace(/&&title&&/g,icon.text);
        },
        position: function () {
            var availHeight = $(window).height();
            var availWidth  = $(window).width();
            var optWinW		= Math.round(availWidth/2);
            var optWinH		= Math.round(availHeight/2);
            var optWinX		= Math.round(availWidth/5);
            var optWinY		= Math.round(availHeight/12)-15;
            for (i=0; i<Desktop.elements.icons.length; i++)  {
                //first we look to see if it is set at the json level, then we look to see if it has already been set, then we check to see if the number
                // are valid, and if not, then use some "optimum" values
                var icon        = Desktop.elements.icons[i];
                var win         = Desktop.window.list[icon.id]; //then place the associated window
                var winWidth	= icon.width ? icon.width : parseInt(win.frame.style.width);
                var winHeight	= icon.height ? icon.height : parseInt(win.frame.style.height);
                var fromTop		= icon.top ? icon.top : parseInt(win.frame.style.top);
                var fromLeft	= icon.left ? icon.left : parseInt(win.frame.style.left);

                winWidth		= isNaN(winWidth) ? optWinW : winWidth;
                winHeight		= isNaN(winHeight) ? optWinH : winHeight;
                fromLeft		= isNaN(fromLeft) ? optWinX : fromLeft;
                fromTop         = isNaN(fromTop) ? optWinY : fromTop;
                win.original.width = winWidth;
                win.original.height = winHeight;
                win.frame.style.position = "absolute";
                win.frame.style.top     = fromTop+"px";
                win.frame.style.left	= fromLeft+"px";
                win.frame.style.width	= winWidth+"px";
                win.frame.style.height	= winHeight+"px";
                win._resize();
            }

        },
        open:   function (evt) {
            evt = (evt) ? evt : ((window.event) ? event : null);
            var win = this;
            var ao = new EasyAjax('/'+this.namespace+'/actions/open');
            ao.add('appid',this.referenceId);
            ao.then(function (response) {
                $E(win.content.id).innerHTML = response;
                ao.executeJavascript();
                if (win.resize) {
                    win.resize();
                }
            });
            ao.post();
        },
        close: function (evt)   {   this.set(''); return true;  },
        resize: function (evt)  {   return true;    },
        minimize: function (evt) {  return true;    },
        maximize: function (evt) {  return true;    },
        set: function (win) {
            win.frame.style.zIndex = 8;
            win.content.style.zIndex = 9;
            if (Desktop.isModern) {
                win.frame.style.opacity = 1.0;
            }
        },
        reset: function () {
            var win;
            for (var i in Desktop.window.list)  {
                win = Desktop.window.list[i];
                if (win.frame) {
                    if (win.frame.style.display === "block")	{
                        win.frame.style.zIndex  = 6;
                        win.content.style.zIndex = 7;
                        if ((virtualWindows.application.windowStyle == "transparent") && (Desktop.isModern)) {
                            win.frame.style.opacity = 0.65;
                        }
                    }
                }
            }
            if (Desktop.drag.window.ref) {
                Desktop.drag.window.ref.frame.style.zIndex = 7;
                Desktop.drag.window.ref.content.style.zIndex = 8;
            }
        },
        cursor: {
            set:    function (evt) {
                evt = (evt) ? evt : ((window.event) ? event : null);
            //    return;
                if (!evt) {
                    return false;
                }
                var thing = Desktop.getDesktopId(evt);
                if (!thing)
                    return false;
                if (!Desktop.window.list[thing]) {
                    return false;
                }
                var win = Desktop.window.list[thing].frame;
                var sX = 0;	var sY = 0;
                if (Desktop.isModern) {
                    evt.stopPropagation();
                    sX = window.pageXOffset;
                    sY = window.pageYOffset;
                } else {
                    evt.cancelBubble = true;
                    sX = Desktop.ref.iebody.scrollLeft;
                    sY = Desktop.ref.iebody.scrollTop;
                }
                var clickX = +sX + evt.clientX;
                var clickY = +sY + evt.clientY;
                var winX = +sX + win.offsetLeft;
                var winY = +sY + win.offsetTop;
                var winR = +winX + win.offsetWidth; //right side
                var winB = +winY + win.offsetHeight;//bottom
                var dX = (clickX - winX);
                var dY = (clickY - winY);
                var rX = (winR - clickX);
                var bY = (winB - clickY);
                var newCursor = "auto";
                if ((dX<6) && (dY<6))
                    newCursor = "nw-resize";
                else if ((dX<5) && (bY > 5))
                    newCursor = "w-resize";
                else if ((rX > 5) && (dY < 5))
                    newCursor = "n-resize";
                else if ((rX < 5) && (dY < 5))
                    newCursor = "ne-resize";
                else if ((rX<5) && (bY < 5))
                    newCursor = "se-resize";
                else if ((dX<5) && (bY<5))
                    newCursor = "sw-resize";
                else if ((rX < 5))
                    newCursor = "e-resize";
                else if (bY < 5)
                    newCursor = "s-resize";
                document.body.style.cursor = newCursor;
            },
            reset: function (evt) {
                evt = (evt) ? evt : ((window.event) ? event : null);
                document.body.style.cursor = "auto";
            }
        },
        drag: {
            start: function (evt) {

                evt = (evt) ? evt : ((window.event) ? event : null);
                var newCursor 	= "";
                var winId = Desktop.getDesktopId(evt);
                if (typeof(winId)==="string") {
                    Desktop.drag.window.current = winId;
                    Desktop.drag.window.ref = Desktop.window.list[winId];
                    Desktop.window.reset();
                    Desktop.window.set(Desktop.drag.window.ref);
                    if (Desktop.isModern) {
                        //evt.stopPropagation();
                        evt.preventDefault();
                    } else {
                        //evt.cancelBubble=true;
                    }

                    Desktop.drag.window.ref.frame.style.zIndex = 8;
                    Desktop.drag.window.scroll.left = Desktop.scrollLeft();
                    Desktop.drag.window.scroll.top  = Desktop.scrollTop();
                    Desktop.drag.window.click.X = +Desktop.drag.window.scroll.left + evt.clientX;
                    Desktop.drag.window.click.Y = +Desktop.drag.window.scroll.top  + evt.clientY;
                    Desktop.drag.window.start.X = Desktop.drag.window.ref.frame.offsetLeft;
                    Desktop.drag.window.start.Y = Desktop.drag.window.ref.frame.offsetTop;
                    Desktop.drag.window.start.H = Desktop.drag.window.ref.frame.offsetHeight;
                    Desktop.drag.window.start.W = Desktop.drag.window.ref.frame.offsetWidth;
                    Desktop.drag.window.delta.X = Desktop.drag.window.click.X - Desktop.drag.window.start.X;
                    Desktop.drag.window.delta.Y = Desktop.drag.window.click.Y - Desktop.drag.window.start.Y;

                    var resizing =  Desktop.drag.window.resize.left   =  (Desktop.drag.window.delta.X < 6);
                    resizing     = ((Desktop.drag.window.resize.right     =  (+Math.abs(Desktop.drag.window.click.X - (Desktop.drag.window.start.X + Desktop.drag.window.start.W))<6)) || resizing);
                    resizing     = ((Desktop.drag.window.resize.top       =  (Desktop.drag.window.delta.Y < 6)) || resizing);
                    resizing     = ((Desktop.drag.window.resize.bottom    =  (+Math.abs(Desktop.drag.window.click.Y - (Desktop.drag.window.start.Y + Desktop.drag.window.start.H)) < 6)) || resizing);
                    Desktop.drag.window.resizing = resizing;

                    if ((Desktop.drag.window.resize.top) && (Desktop.drag.window.resize.left))
                        newCursor = "nw-resize";
                    else if ((Desktop.drag.window.resize.right) && (Desktop.drag.window.resize.bottom))
                        newCursor = "se-resize";
                    else if ((Desktop.drag.window.resize.right) && (Desktop.drag.window.resize.top))
                        newCursor = "ne-resize";
                    else if ((Desktop.drag.window.resize.left) && (Desktop.drag.window.resize.bottom))
                        newCursor = "sw-resize";
                    else if (Desktop.drag.window.resize.left)
                        newCursor = "w-resize";
                    else if (Desktop.drag.window.resize.top)
                        newCursor = "n-resize";
                    else if (Desktop.drag.window.resize.right)
                        newCursor = "e-resize";
                    else if (Desktop.drag.window.resize.bottom)
                        newCursor = "s-resize";
                    else
                        newCursor = "move";

                    Desktop.ref.onmouseup   =  Desktop.window.drag.stop;
                    Desktop.ref.onmousemove = Desktop.window.drag.active;
                    Desktop.off(Desktop.drag.window.ref.frame,"mousedown", Desktop.window.drag.start);
                    document.body.style.cursor = newCursor;
                    if (Desktop.window.list[winId]) {
                        Desktop.window.list[winId]._resize();
                    }
                }
                return false;
            },
            active: function (evt) {
                evt = (evt) ? evt : ((window.event) ? event : null);

                Desktop.drag.window.scroll.left = Desktop.scrollLeft();
                Desktop.drag.window.scroll.top  = Desktop.scrollTop();

                var newSize = 0;
                var newPosX = (evt.clientX - Desktop.drag.window.delta.X);
                var newPosY = (evt.clientY - Desktop.drag.window.delta.Y);

                if ((Desktop.drag.window.scroll.left + newPosX) <= 1) newPosX = 1;
                if ((Desktop.drag.window.scroll.top+ newPosY) <= 1) newPosY = 1;
                newPosX +="px";
                newPosY +="px";

                if (Desktop.drag.window.resize.left) {
                    newSize = (Desktop.drag.window.start.W  + (Desktop.drag.window.start.X - (parseInt(evt.clientX) + Desktop.drag.window.scroll.left))) + "px";
                    if (parseInt(newSize) < 20) newSize = "20px";
                    Desktop.drag.window.ref.frame.style.left	= Desktop.drag.window.scroll.left + parseInt(newPosX)+"px";
                    Desktop.drag.window.ref.frame.style.width 	= newSize;
                }
                if (Desktop.drag.window.resize.right)  {
                    newSize	= Desktop.drag.window.start.W + ((parseInt(evt.clientX) - Desktop.drag.window.start.X - Desktop.drag.window.scroll.left - Desktop.drag.window.start.W)) + "px";
                    if (parseInt(newSize) < 20) {
                        newSize 	= "20px";
                    }
                    Desktop.drag.window.ref.frame.style.width 	= newSize;
                }
                if (Desktop.drag.window.resize.top) {
                    newSize	= (Desktop.drag.window.start.H + Desktop.drag.window.start.Y - parseInt(evt.clientY)  + Desktop.drag.window.delta.Y) + "px";
                    if (parseInt(newSize) < 25) {
                        newSize = "25px";
                    } else {
                        Desktop.drag.window.ref.frame.style.top	= (Desktop.drag.window.scroll.top + parseInt(newPosY)) + "px";
                    }
                    Desktop.drag.window.ref.frame.style.height	= newSize;
                }
                if (Desktop.drag.window.resize.bottom)  {
                    newSize	= Desktop.drag.window.start.H + ((parseInt(evt.clientY) - Desktop.drag.window.start.Y - Desktop.drag.window.scroll.top - Desktop.drag.window.start.H)) + "px";
                    if (parseInt(newSize) < 25) {
                        newSize = "25px";
                    }
                    Desktop.drag.window.ref.frame.style.height	= newSize;
                }
                if (!(Desktop.drag.window.resizing))  {
                    if (Desktop.drag.window.ref)  {
                        var newX = parseInt(Desktop.drag.window.scroll.left) + parseInt(newPosX);
                        var newY = parseInt(Desktop.drag.window.scroll.top)  + parseInt(newPosY);
                        Desktop.drag.window.ref.frame.style.left= newX + "px";
                        Desktop.drag.window.ref.frame.style.top	= newY + "px";
                    }
                } else {
                    Desktop.drag.window.ref._resize();
                }
                return false;
            },
            stop: function (evt) {
                evt = (evt) ? evt : ((window.event) ? event : null);
                Desktop.window.reset();
                Desktop.window.set(Desktop.drag.window.ref);
                Desktop.on(Desktop.drag.window.ref.frame,"mousedown", Desktop.window.drag.start);
                Desktop.ref.onmousemove = null;
                Desktop.ref.onmouseup = null;
                var win = Desktop.drag.window.ref.frame; //shorthand
                if ((Desktop.drag.window.start.X !== win.offsetLeft) || (Desktop.drag.window.start.Y !== win.offsetTop) || (Desktop.drag.window.start.W !== win.offsetWidth) || (Desktop.drag.window.start.H !== win.offsetHeight))  {
                    //(new WindowAttributeClass(Desktop.drag.window.ref.frame.id,win.offsetLeft,win.offsetTop,win.offsetWidth,win.offsetHeight)).save();
                }
                return false;
            }
        }
    }
};
//oh boy!
$(document).ready(Desktop.init);
window.onbeforeunload   = Desktop.promptBeforeLeaving;
window.onunload         = Desktop.unload;