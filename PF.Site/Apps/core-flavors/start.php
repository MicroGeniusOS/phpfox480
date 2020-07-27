<?php

event('Core\View\Loader::getSource', function (\Core\View\Loader $loader) {
    $loader->layout = flavor()->active->html_path();
});

event('Core\View\Loader::getCustomSource', function (\Core\View\Loader $loader, $name) {
    $origin_file = flavor()->active->html_path();
    $file_to_load = str_replace('layout.html', $name, $origin_file);
    if (cached_file_exists($file_to_load)) {
        $loader->layout = $file_to_load;
    }
});

event('view_cache_path', function () {
    Core\View::$cache_path = PHPFOX_DIR_CACHE . 'twig' . PHPFOX_DS . flavor()->active->id . PHPFOX_DS;
});

group('/flavors', function () {

    route('/manage', function () {
        auth()->isAdmin(true);

        $flavor = flavor()->get(request()->get('id'));
        if ($flavor === false) {
            return url()->send('/admincp/theme/');
        }

        if (request()->get('type')) {
            if (Phpfox::demoModeActive()) {
                return url()->send('admincp', 'AdminCP is set to "Demo Mode". This action is not permitted when the site is in this mode.');
            }
            $html = '';
            $title = '';
            $ace = false;
            $save = url()->make('/flavors/manage', ['id' => $flavor->id, 'type' => request()->get('type')]);
            $mode = 'html';
            switch (request()->get('type')) {
                case 'revert':
                    if (request()->get('process')) {
                        $flavor->revert();
                        return url()->send('/flavors/manage', ['id' => $flavor->id], _p('Theme successfully reverted'));
                    }
                    title(_p('Revert'));
                    return view('revert.html', [
                        'flavor' => $flavor
                    ]);
                    break;
                case 'revert_pre_version':
                    if (request()->get('process')) {
                        $flavor->revert_pre_version(request()->get('val'));
                        return url()->send('/flavors/manage', ['id' => $flavor->id], _p('Theme successfully reverted'));
                    }
                    title(_p('Revert to The Previous Version'));
                    return view('revert_pre_version.html', [
                        'flavor'             => $flavor,
                        'show_logo'          => $flavor->has_logo_revision(),
                        'show_favicon'       => $flavor->has_favicon_revision(),
                        'show_content'       => $flavor->has_content_revision(),
                        'show_banner'        => $flavor->has_banner_revision(),
                        'show_default_photo' => $flavor->has_defaults_revision(),
                        'show_design'        => isset($flavor->vars) && count((array)$flavor->vars) && $flavor->has_design_revision(),
                        'show_css'           => $flavor->has_css_revision(),
                        'show_js'            => $flavor->has_js() && $flavor->has_js_revision(),
                        'show_html'          => $flavor->has_html_revision(),

                    ]);
                    break;
                case 'default':
                    storage()->del('flavor/default');
                    storage()->set('flavor/default', $flavor->id);
                    flavor()->set_active($flavor->id);
                    flavor()->rebuild_bootstrap(true);
                    cache()->purge();

                    return [
                        'run' => 'location.reload();'
                    ];
                    break;
                case 'export':
                    $flavor->export();
                    break;
                case 'delete_banner':
                    $flavor->save('delete_banner', []);
                    $dir = $flavor->path . 'assets/banners/';
                    $banner = str_replace(home() . 'PF.Site/flavors/' . $flavor->id . '/assets/banners/', '', request()->get('banner'));
                    $file = $dir . $banner;
                    if (file_exists($file)) {
                        unlink($file);
                    }

                    return [
                        'success' => 'true'
                    ];
                    break;
                case 'delete':
                    if (request()->get('process')) {
                        $flavor->delete();

                        return url()->send('/admincp/theme/', _p('Theme successfully deleted'));
                    }

                    title(_p('Delete'));

                    return view('delete.html', [
                        'flavor' => $flavor
                    ]);
                    break;
                case 'settings':
                    title(_p('Advanced Settings'));

                    if (request()->isPost()) {
                        $flavor->save('settings', request()->get('content'));

                        return [
                            'run' => "$('.js_box_content').html('<div class=\"message\">" . _p('Settings successfully saved.') . "</div>'); setTimeout(tb_remove, 2000);"
                        ];
                    }

                    return view('settings.html', [
                        'flavor' => $flavor,
                        'json'   => $flavor->json()
                    ]);
                    break;
                case 'icon':
                    if (!empty($_FILES['ajax_upload'])) {
                        $url = $flavor->save('icon', $_FILES['ajax_upload']);

                        return [
                            'run' => 'Theme_Manager.icon(\'' . $url . '\')'
                        ];
                    }
                    break;
                case 'content':
                    $flavor->save('content', request()->get('content'));

                    return [
                        'success' => true
                    ];
                    break;
                case 'homepage':
                    if (!empty($_FILES['ajax_upload'])) {
                        $banner = $flavor->save('banners', $_FILES['ajax_upload']);

                        $params = [
                            'banner' => rawurlencode($banner)
                        ];
                        return [
                            'run' => "Theme_Manager.banner(" . json_encode($params) . ");"
                        ];
                    }

                    $title = _p('homepage');
                    $html = view('@PHPfox_Flavors/homepage.html', [
                        'flavor'  => $flavor,
                        'banners' => $flavor->banners(),
                        'content' => $flavor->content()
                    ]);
                    break;
                case 'logo':
                    if (!empty($_FILES['ajax_upload'])) {
                        $flavor->save('logos', $_FILES['ajax_upload'], request()->get('sub_type'));

                        $params = [
                            'type'    => request()->get('sub_type'),
                            'logo'    => $flavor->logo_url('logos', true),
                            'favicon' => $flavor->favicon_url(true)
                        ];
                        return [
                            'run' => "Theme_Manager.logo(" . json_encode($params) . ");"
                        ];
                    }

                    $title = _p('logos');
                    $html = view('@PHPfox_Flavors/logo.html', [
                        'flavor'  => $flavor,
                        'logo'    => $flavor->logo_url(),
                        'favicon' => $flavor->favicon_url()
                    ]);
                    break;

                // support set default photo for theme
                case 'default_photo':
                    $aPhotos = [
                        'user_cover_default' => [
                            'title' => _p('User Cover Default Photo'),
                            'value' => $flavor->default_photo('user_cover_default', true),
                        ]
                    ];

                    if (\Phpfox::isAppActive('Core_Pages')) {
                        $aPhotos['pages_cover_default'] = [
                            'title' => _p('Pages Cover Default Photo'),
                            'value' => $flavor->default_photo('pages_cover_default', true),
                        ];
                    }

                    if (\Phpfox::isAppActive('PHPfox_Groups')) {
                        $aPhotos['groups_cover_default'] = [
                            'title' => _p('Groups Cover Default Photo'),
                            'value' => $flavor->default_photo('groups_cover_default', true),
                        ];
                    }

                    (($sPlugin = \Phpfox_Plugin::get('theme_get_default_photos_list')) ? eval($sPlugin) : false);

                    if (!empty($_FILES['ajax_upload'])) {
                        $flavor->save('default_photo', $_FILES['ajax_upload'], request()->get('sub_type'));
                        $params = [
                            'type' => request()->get('sub_type'),
                            'file' => $flavor->default_photo(request()->get('sub_type'), true) . '?v=' . uniqid(),
                        ];
                        return [
                            'run' => "Theme_Manager.default_photo(" . json_encode($params) . ");"
                        ];
                    }

                    $title = _p('default_photos');
                    $html = view('@PHPfox_Flavors/default_photo.html', [
                        'flavor' => $flavor,
                        'photos' => $aPhotos
                    ]);
                    break;

                // support set default photo for theme
                case 'remove_default':
                    $flavor->save('remove_default', '', request()->get('sub_type'));
                    $params = [
                        'type' => request()->get('sub_type'),
                        'file' => '',
                    ];
                    return [
                        'run' => "Theme_Manager.default_photo(" . json_encode($params) . ");"
                    ];
                    break;

                case 'design':
                    if (request()->isPost()) {
                        $flavor->save(request()->get('type'), request()->get('var'));

                        return [
                            'run' => 'Theme_Manager.design();'
                        ];
                    }

                    $title = _p('design');
                    $html = $flavor->design();
                    break;
                case 'css':
                    $title = _p('css');
                    if (request()->isPost()) {
                        $flavor->save('css', request()->get('content', '', false));

                        return [
                            'run' => 'Theme_Manager.success();'
                        ];
                    }

                    $ace = $flavor->css();
                    $mode = 'css';
                    break;
                case 'js':
                    if (request()->isPost()) {
                        $flavor->save('js', request()->get('content', '', false));

                        return [
                            'run' => 'Theme_Manager.success();'
                        ];
                    }

                    $title = _p('javascript');
                    $ace = $flavor->js();
                    $mode = 'javascript';
                    break;
                case 'html':
                    if (request()->isPost()) {
                        $flavor->save('html', request()->get('content', '', false));

                        return [
                            'run' => 'Theme_Manager.success();'
                        ];
                    }

                    $title = _p('html');
                    $ace = $flavor->html(true);
                    break;
            }

            return [
                'type'  => request()->get('type'),
                'html'  => $html,
                'title' => $title,
                'ace'   => $ace,
                'save'  => $save,
                'mode'  => $mode
            ];
        }

        \Core\View::$template = 'blank';

        asset('<link href="' . home() . 'PF.Base/static/jscript/colorpicker/css/colpick.css" rel="stylesheet">');
        asset('@static/colorpicker/js/colpick.js');

        $has_upgrade = false;
        $cacheService = Phpfox::getLib('cache');
        $storeThemeId = $cacheService->set('store_theme_' . $flavor->id);
        $store = $cacheService->get($storeThemeId, 1440);
        if ($store == false) {
            $store_url = Core\Home::store() . 'product/' . $flavor->id . '/view.json';
            if (@get_headers($store_url)) {
                $store = json_decode(@fox_get_contents($store_url), true);
                $cacheService->save($storeThemeId, $store);
                $cacheService->group('admincp', $storeThemeId);
            }
        }

        if (isset($store['id']) && version_compare($flavor->version, $store['version'], '<')) {
            $Home = new Core\Home(PHPFOX_LICENSE_ID, PHPFOX_LICENSE_KEY);
            $response = $Home->admincp(['return' => url('admincp.app.add')]);
            $store['install_url'] = $store['url'] . '/installing?iframe-mode=' . $response->token;
            $has_upgrade = true;
        }

        Phpfox::getLib('template.cache')->remove();
        $cacheService->removeStatic();

        return view('manage.html', [
            'flavor'                  => $flavor,
            'show_design'             => (isset($flavor->vars) && count((array)$flavor->vars) ? true : false),
            'show_js'                 => $flavor->has_js(),
            'show_revert_pre_version' => $flavor->has_pre_version(),
            'has_upgrade'             => $has_upgrade && !defined('PHPFOX_TRIAL_MODE'),
            'store'                   => $store,
            'active_flavor_id'        => flavor()->active->id
        ]);
    });
});

group('/admincp/theme', function () {
    route('/bootstrap/rebuild', function () {

        if (Phpfox::demoModeActive()) {
            return url()->send('admincp', 'AdminCP is set to "Demo Mode". This action is not permitted when the site is in this mode.');
        }

        auth()->isAdmin(true);

        try {
            flavor()->rebuild_bootstrap(true);
            if (PHPFOX_IS_AJAX_PAGE) {
                return [
                    'run' => 'flavor_end();'
                ];
            } else {
                Phpfox::addMessage(_p('successfully_rebuilt_core_theme'));
                url()->send('admincp');
            }
        } catch (\Exception $ex) {
            return [
                'run' => sprintf('flavor_alert("%s")', base64_encode($ex->getMessage()))
            ];
        }

        return null;
    });

    route('/manage', function () {
        auth()->isAdmin(true);
        url()->send('/flavors/manage', ['id' => request()->get('id')]);
    });

    route('/add', function () {
        if (Phpfox::demoMode()) {
            return false;
        }

        auth()->isAdmin(true);
        title(_p('New Theme'));

        $file = null;
        if (!empty($_FILES['ajax_upload'])) {
            $file = $_FILES['ajax_upload'];
        } else if (request()->get('download')) {
            $path = PHPFOX_DIR_FILE . 'static' . PHPFOX_DS . uniqid() . '.zip';
            file_put_contents($path, fox_get_contents(request()->get('download', '', false)));
            $file = [
                'is_local' => true,
                'tmp_name' => $path
            ];
        }

        if (request()->isPost() || $file) {
            $flavor = flavor()->make(request()->get('val', []), $file);
            return url()->send('/flavors/manage', ['id' => $flavor->id]);
        }

        $themes = [];
        $themes['__blank'] = _p('Blank Theme');
        foreach (flavor()->all() as $flavor) {
            $themes[$flavor->id] = $flavor->name;
        }
        return view('add.html', [
            'themes' => $themes
        ]);
    });
});
