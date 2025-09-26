//jetpack fix
_.contains = _.includes;
window.lodash = _.noConflict();
window.addEventListener('load', LVJM_pageImportVideos, false);

function LVJM_pageImportVideos() {

    if (document.getElementById('import-videos')) {

        jQuery('body').tooltip({
            selector: '[rel=tooltip]',
            container: 'body',
            trigger: 'hover'
        });

        //sticky header
        if (jQuery('#videos-found').length > 0) {
            var $headerNav = jQuery('#videos-found-header');
        }

        var stickNav = function () {
            if (jQuery('#videos-found').is(':visible')) {
                var stickyNavTop = jQuery('#videos-found').offset().top - $headerNav.height() - 32;
                var scrollTop = jQuery(window).scrollTop()
                var $totalWidth = jQuery('#import-videos').width();
                if (scrollTop > stickyNavTop) {
                    jQuery('#videos-found-header').addClass('sticky').width($totalWidth - 60);
                    jQuery('#sticky-space').height('152px');
                } else {
                    jQuery('#videos-found-header').removeClass('sticky');
                    jQuery('#videos-found-header').width('100%');
                    jQuery('#sticky-space').height('0px');
                }
            }
        }

        //on window load / scroll / resize
        jQuery(window).on('load scroll resize', function (e) {
            stickNav();
        });

        //vue script
        var importVideos = new Vue({
            el: '#import-videos',
            data: {
                //loading
                loading: {
                    loadingData: false,
                    deleteFeed: false,
                    removingVideo: false
                },
                ///data
                dataLoaded: false,
                data: {},

                // updates
                updates: {
                    show_1_3_1: false
                },

                //misc
                searchFromFeed: false,
                firstImport: true,
                showSavedSearchesHelp: false,
                displayType: 'cards',

                //pagination
                paginate: ['feeds'],

                //feeds
                updatingFeedId: '',
                feedsFilter: '',
                deleteFeedId: '',

                //partners
                showPartnersFilters: false,
                partnersFilters: {
                    language: '',
                    mobile_ready: '',
                    orientation: '',
                    https: '',
                    multithumbs: '',
                    trailer: ''
                },
                sortPartners: 'popularity',
                selectedPartner: 'befuck',

                partnerConfigLoading: false,
                selectedPartnerConfig: '',

                partnerCatsLoading: false,
                partnerCats: [],
                selectedPartnerCats: '',

                // allPartnersCounter: 0,
                filteredPartnersCounter: 0,
                partnersFiltersCounter: 0,

                //categories & keywords
                selectedCat: '',
                selectedKW: '',
                selectedPerformer: '',
                selectedWPCat: 0,
                selectedPostsStatus: '',
                newWpCategoryName: '',
                addingNewWpCategory: false,

                //videos
                videos: [],
                searchingVideos: false,
                videosHasBeenSearched: false,
                videosSearchedErrors: {},
                searchedData: {},

                currentVideo: '',
                currentVideoUrl: '',
                currentVideoEmbed: '',
                expandedThumb: '',
                videoTab: 'data',

                deleteVideo: {},
                removeDeleteVideoConfirmation: false,

                videoFilterKW: '',
                videosFilters: [],
                importingVideos: false,
                savedCheckedVideosCounter: 0
            },
            computed: {
                siteIsHttps: function () {
                    return window.location.protocol == "https:";
                },
                smtIsLoading: function () {
                    return this.loading.loadingData || this.loading.deleteFeed || this.partnerCatsLoading || this.searchingVideos;
                },
                filteredPartners: function () {
                    var self = this;

                    self.resetFilters();
                    self.partnersFiltersCounter = 0;

                    lodash.each(self.partnersFilters, function (filter_value, filter_key) {
                        if (filter_value != '') {
                            self.partnersFiltersCounter++;

                            lodash.each(self.data.partners, function (partner_filters, partner_id) {
                                var keep_partner = false;
                                switch (typeof partner_filters.filters[filter_key]) {
                                    case 'undefined':
                                        break;
                                    case 'string':
                                        if (partner_filters.filters[filter_key] == filter_value) {
                                            keep_partner = true;
                                        }
                                        break;
                                    case 'number':
                                        if (partner_filters.filters[filter_key] == filter_value) {
                                            keep_partner = true;
                                        }
                                        break;
                                    case 'boolean':
                                        if (partner_filters.filters[filter_key] == filter_value) {
                                            keep_partner = true;
                                        }
                                        break;
                                    case 'object':
                                        if (partner_filters.filters[filter_key].indexOf(filter_value) > -1) {
                                            keep_partner = true;
                                        }
                                        break;
                                }
                                if (!keep_partner) {
                                    self.data.partners[partner_id].show = false;
                                }

                            });
                        }
                    });
                    switch (self.sortPartners) {
                        case 'popularity':
                            self.data.partners = lodash.orderBy(self.data.partners, ['popularity'], ['desc']);
                            break;
                        case 'alpha':
                            self.data.partners = lodash.orderBy(self.data.partners, ['id'], ['asc']);
                            break;
                    }

                    self.selectedPartner = self.data.partners[0].id;

                    setTimeout(function () {
                        jQuery('#partner_select').selectpicker('refresh');
                        self.filteredPartnersCounter = jQuery('#partner_select option').length;
                    }, 0);


                    return self.data.partners;
                },
                selectedPartnerObject: function () {
                    var self = this;
                    return lodash.find(this.data.partners, function (p) {
                        return p.id == self.selectedPartner;
                    });
                },
                checkedVideosCounter: function () {
                    return this.videos.filter(function (video) {
                        return video.checked;
                    }).length;
                },
                selectedPartnerCatName: function () {
                    var self = this;
                    if (this.selectedPartnerObject != '') {
                        var name;
                        lodash.each(self.selectedPartnerObject.categories, function (c) {
                            if (lodash.has(c, 'sub_cats')) {
                                lodash.each(c.sub_cats, function (sc) {
                                    if (sc.id == self.selectedCat) {
                                        name = sc.name;
                                    }
                                });
                            } else {
                                if (c.id == self.selectedCat)
                                    name = c.name;
                            }
                        });
                        return name;
                    }
                    return '';
                },
                selectedWPCatName: function () {
                    self = this;
                    if (this.selectedWPCat > 0) {
                        return lodash.find(self.data.WPCats, function (wpcat) {
                            return wpcat.term_id == self.selectedWPCat;
                        }).name;
                    }
                    return '...';
                },
                importProgress: function () {
                    var progress = (this.savedCheckedVideosCounter - this.checkedVideosCounter) / this.savedCheckedVideosCounter * 100;
                    if (this.importingVideos) {
                        return progress >= 100 ? 100 : progress;
                    }
                    return 0;
                },
                checkableVideosCounter: function () {
                    return this.videos.filter(function (video) {
                        return !video.grabbed;
                    }).length;
                },
                allVideosChecked: function () {
                    return ((this.checkedVideosCounter == this.checkableVideosCounter) && (this.checkableVideosCounter > 0));
                },
                videosCounter: function () {
                    return this.videos.length;
                },
                searchBtnClass: function () {
                    if (this.selectedCat == '' && this.selectedKW == '') {
                        return 'disabled';
                    }
                    return '';
                },
                importBtnClass: function () {
                    if (this.checkedVideosCounter <= 0 || this.selectedWPCat == 0 || this.importingVideos) {
                        return 'disabled';
                    }
                    return '';
                },
                searchButtonTooltip: function () {
                    self = this;
                    if( this.selectedCat !== '' || this.selectedKW !== '' ) return '';

                    return this.data.objectL10n.select_cat_from + ' ' + lodash.find(this.data.partners, function (p) {
                        return p.id == self.selectedPartner
                    }).name + ' ' + this.data.objectL10n.or_keyword_if_available;
                },
                importButtonTooltip: function () {
                    var title = '';
                    if (this.selectedWPCat == 0) {
                        title += this.data.objectL10n.select_wp_cat;
                    }
                    if (this.checkedVideosCounter == 0 && this.selectedWPCat == 0) {
                        title += ' ' + this.data.objectL10n.and + ' ';
                    }
                    if (this.checkedVideosCounter == 0) {
                        title += this.data.objectL10n.check_least;
                    }
                    if (this.checkedVideosCounter == 0 || this.selectedWPCat == 0) {
                        title += ' ' + this.data.objectL10n.enable_button;
                    }

                    if (title == '' && this.firstImport) {
                        title = this.data.objectL10n.import+' ' + this.checkedVideosCounter + ' ' + this.data.objectL10n.search_feed;
                    }

                    if (!this.importingVideos)
                        return title;

                    return '';
                },
                filteredFeeds: function () {
                    var self = this;
                    var filteredFeeds = lodash.filter(this.data.feeds, function (f) {
                        return (
                            f.id.toLowerCase().search(self.feedsFilter.toLowerCase()) > -1 ||
                            f.last_update.toLowerCase().search(self.feedsFilter.toLowerCase()) > -1 ||
                            f.status.toLowerCase().search(self.feedsFilter.toLowerCase()) > -1 ||
                            f.total_videos.toString().search(self.feedsFilter) > -1
                        )
                    });
                    return lodash.orderBy(filteredFeeds, ['last_update'], ['desc']);
                }
            },
            filters: {
                timeFormat: function (timeInSeconds) {
                    var date = new Date(1970, 0, 1);
                    date.setSeconds(timeInSeconds);
                    return date.toTimeString().replace(/.*(\d{2}:\d{2}:\d{2}).*/, "$1");
                },
                listFormat: function (list) {
                    return list.replace(/;/g, ',').split(',').join(', ');
                },
                addZero: function (number) {
                    if (number < 10)
                        number = '0' + number;
                    return number;
                }
            },
            methods: {
                loadPartnerCats: function () {
                    this.partnerCatsLoading = true;
                    
                    // Injected: support for All Straight Categories
                    if (this.selectedPartnerCats === 'all_straight') {
                        postData.multi_category_search = 1;
                    }

                    this.$http.post(
    
                            LVJM_import_videos.ajax.url, {
                                action: 'lvjm_load_partner_cats',
                                nonce: LVJM_import_videos.ajax.nonce,
                                partner_id: this.selectedPartnerObject.id,
                                method: this.searchFromFeed ? 'update' : 'create'
                            }, {
                                emulateJSON: true
                            }
                        )
                        .then(function (response) {
                            // success callback
                            this.partnerCats = response.body;
                        }, function (response) {
                            // error callback
                            console.error(response);
                        }).then(function () {
                            this.partnerCatsLoading = false;
                            setTimeout(function () {
                                jQuery('#cat_s_select').selectpicker('refresh');
                            }, 0);
                        });
                },
                resetSearch: function () {
                    this.videos = [];
                    this.searchFromFeed = false;
                    this.isTestSearch = false;
                    this.videosHasBeenSearched = false;

                    setTimeout(function () {
                        jQuery('#partner_select').selectpicker('refresh');
                    }, 0);

                    this.selectedCat = '';
                    this.selectedWPCat = 0;
                    this.selectedKW = '';
                    this.selectedPostsStatus = '';

                    this.searchedData = {};

                    //change selected partner for the watcher
                    jQuery('#cat_wp_select').selectpicker('val', '0');
                    jQuery('#cat_s_select').selectpicker('val', '');
                },
                resetFilters: function () {
                    var self = this;
                    lodash.each(self.data.partners, function (partner_filters, partner_id) {
                        self.data.partners[partner_id].show = true;
                    });
                },
                togglePartnersFilters: function () {
                    this.showPartnersFilters = !this.showPartnersFilters;
                    return;
                },
                toggleSavedSearchesHelp: function () {
                    this.showSavedSearchesHelp = !this.showSavedSearchesHelp;
                    return;
                },
                searchVideos: function (method, feedId) {

                    if (this.searchBtnClass == 'disabled' && method != 'update') return;

                    if( method == 'update' ) window.scrollTo(0, jQuery('#search-top').offset().top - 25);

                    var cat_s = '';
                    var kw = '';
                    var partnerId = '';

                    //reset searching states
                    this.videos = [];
                    this.searchingVideos = true;
                    this.videosHasBeenSearched = false;

                    if (feedId === undefined) {

                        cat_s = this.selectedKW != '' ? this.selectedKW.toLowerCase().split(' ').join(this.selectedPartnerObject.filters.search_sep).trim() : this.selectedCat;
                        kw = this.selectedKW != '' ? 1 : 0;
                        partner = this.selectedPartnerObject;

                        jQuery('[data-id="sort_partners"]').prop("disabled", true);
                        jQuery('[data-id="cat_s_select"]').prop("disabled", true);
                        jQuery('[data-id="partner_select"]').prop("disabled", true);

                    } else {

                        var feed = lodash.find(this.data.feeds, function (f) {
                            return f.id == feedId
                        });

                        this.updatingFeedId = feedId;

                        cat_s = feed.partner_cat;
                        kw = feed.partner_cat.indexOf('kw::') >= 0 ? 1 : 0;
                        partner = lodash.find(this.data.partners, function (p) {
                            return p.id == feed.partner_id
                        });

                        //set search UI datas
                        this.selectedPartner = feed.partner_id;
                        this.selectedPostsStatus = feed.status;
                        this.selectedWPCat = feed.wp_cat;
                        if (kw == 1) {
                            cat_s = cat_s.replace('kw::', '');
                            this.selectedKW = cat_s;
                        } else {
                            this.selectedCat = cat_s;
                        }
                    }

                    if (method == 'update') {
                        this.searchFromFeed = true;
                    }

                    //reset tooltips states
                    //jQuery('[rel=tooltip]').tooltip('hide');

                    
                    // Injected: support for All Straight Categories
                    if (this.selectedPartnerCats === 'all_straight') {
                        postData.multi_category_search = 1;
                    }

                    this.$http.post(
    
                            LVJM_import_videos.ajax.url, {
                                action: 'lvjm_search_videos',
                                cat_s: cat_s,
                                feed_id: feedId,
                                from: 'manual',
                                kw: kw,
                                limit: this.data.videosLimit,
                                method: method,
                                nonce: LVJM_import_videos.ajax.nonce,
                                original_cat_s: cat_s.replace('&', '%%'),
                                partner: partner,
                                performer: this.selectedPerformer
                            }, {
                                emulateJSON: true
                            })
                        .then(function (response) {
                            var videos = this.videos;
                            if (lodash.isEmpty(response.body.errors)) {
                                this.searchedData = response.body.searched_data;
                                lodash.each(response.body.videos, function (video) {
                                    videos.push({
                                        id: video.id,
                                        title: video.title,
                                        thumb_url: video.thumb_url,
//                                        thumbs_urls: video.thumbs_urls.split(','),
                                        thumbs_urls: video.thumbs_urls,
                                        trailer_url: video.trailer_url,
                                        desc: video.desc,
                                        embed: video.embed,
                                        tracking_url: video.tracking_url,
                                        duration: video.duration,
                                        quality: video.quality,
                                        isHd: video.isHd,
                                        uploader: video.uploader,
                                        actors: video.actors,
                                        tags: video.tags,
                                        video_url: video.video_url,
                                        checked: video.checked,
                                        grabbed: false,
                                        loading: {
                                            removing: false
                                        }
                                    });
                                });
                            } else {
                                this.videosSearchedErrors = response.body.errors;
                            }
                            delete errors;
                            delete response;
                            delete videos;
                            if (method == 'create') {
                                this.firstImport = true;
                            } else {
                                this.firstImport = false;
                            }
                        }, function (response) {
                            // error callback
                            console.error(response);
                        }).
                    then(function () {
                        this.videosHasBeenSearched = true;
                        this.searchingVideos = false;
                        jQuery('[data-id="sort_partners"]').prop("disabled", false);
                        jQuery('[data-id="cat_s_select"]').prop("disabled", false);
                        jQuery('[data-id="partner_select"]').prop("disabled", false);
                        stickNav();
                    });
                },
                updateDisplayType: function (displayType) {
                    this.displayType = displayType;
                },
                addNewWpCategory: function () {
                    this.addingNewWpCategory = true;
                    var self = this;
                    self.$http.post(
                            LVJM_import_videos.ajax.url, {
                                action: 'lvjm_create_category',
                                nonce: LVJM_import_videos.ajax.nonce,
                                category_name: self.newWpCategoryName,
                            }, {
                                emulateJSON: true
                            })
                        .then(function (response) {
                            // success callback
                            this.data.WPCats = response.body.wp_cats;
                            setTimeout(function () {
                                jQuery('#cat_wp_select').selectpicker('refresh').selectpicker('val', response.body.new_cat_id);
                            }, 300);
                        }, function (response) {
                            // error callback
                            console.error(response);
                        }).then(function () {
                            jQuery('#add-wp-cat-modal').modal('hide');
                            self.newWpCategoryName = '';
                            self.addingNewWpCategory = false;
                        });
                },
                toggleVideo: function (index, from) {
                    this.videos[index].checked = !this.videos[index].checked;
                },
                toogleAllVideos: function () {
                    var allVideosChecked = !this.allVideosChecked;
                    lodash.each(this.videos, function (video) {
                        if (!video.grabbed) {
                            video.checked = allVideosChecked;
                        }
                    });
                },
                setCurrentVideo: function (video, index) {
                    this.currentVideo = video;
                    this.currentVideoUrl = video.video_url ? video.video_url : null;
                    this.currentVideoEmbed = video.embed ? video.embed : null;
                    this.currentVideo.index = index;
                    this.expandedThumb = '';
                    this.activateVideoTab();
                },
                confirmVideoDeletion: function (video, index) {
                    this.$set(this.deleteVideo, 'video', video);
                    this.$set(this.deleteVideo, 'index', index);
                    if( this.removeDeleteVideoConfirmation ) {
                        this.removeVideo();
                    } else {
                        setTimeout(function () {
                            jQuery('#delete-video-modal').modal('show');
                        }, 0);
                    }
                },
                removeVideo: function () {
                    var video = this.deleteVideo.video;
                    var index = this.deleteVideo.index;

                    video.loading.removing = true;
                    this.loading.removingVideo = true;
                    
                    // Injected: support for All Straight Categories
                    if (this.selectedPartnerCats === 'all_straight') {
                        postData.multi_category_search = 1;
                    }

                    this.$http.post(
    
                            LVJM_import_videos.ajax.url, {
                                action: 'lvjm_remove_video',
                                nonce: LVJM_import_videos.ajax.nonce,
                                video_id: video.id,
                                partner_id: this.selectedPartner
                            }, {
                                emulateJSON: true
                            })
                        .then(function (response) {
                            // success callback
                            this.videos.splice(index, 1);
                            jQuery('#delete-video-modal').modal('hide');
                        }, function (response) {
                            // error callback
                            video.loading.removing = false;
                            console.error(response);
                        }).then(function () {
                            this.loading.removingVideo = false;
                            this.deleteVideo = {};
                        });

                },
                setVideoTab: function (type) {
                    this.videoTab = type;
                },
                activateVideoTab: function () {
                    var self = this;
                    setTimeout(function () {
                        jQuery('#tab-video-' + self.videoTab).trigger('click');
                    }, 0);
                },
                prevVideoModal: function (index) {
                    var prevIndex = null;
                    if (index <= 0) {
                        prevIndex = this.videos.length - 1;
                    } else {
                        prevIndex = index - 1;
                    }
                    this.currentVideo = this.videos[prevIndex];
                    this.currentVideoUrl = this.videos[prevIndex].video_url ? this.videos[prevIndex].video_url : null;
                    this.currentVideoEmbed = this.videos[prevIndex].embed ? this.videos[prevIndex].embed : null;
                    this.currentVideo.index = prevIndex;
                    this.expandedThumb = '';
                    this.activateVideoTab();
                },
                nextVideoModal: function (index) {
                    var nextIndex = null;
                    if (index >= this.videos.length - 1) {
                        nextIndex = 0;
                    } else {
                        nextIndex = index + 1;
                    }
                    this.currentVideo = this.videos[nextIndex];
                    this.currentVideoUrl = this.videos[nextIndex].video_url ? this.videos[nextIndex].video_url : null;
                    this.currentVideoEmbed = this.videos[nextIndex].embed ? this.videos[nextIndex].embed : null;
                    this.currentVideo.index = nextIndex;
                    this.expandedThumb = '';
                    this.activateVideoTab();
                },
                showThumb: function (thumb) {
                    this.expandedThumb = thumb;
                },
                hideThumb: function () {
                    this.expandedThumb = '';
                },
                importVideos: function () {
                    if (this.importBtnClass == 'disabled') {
                        return;
                    }

                    //doing DOM stuff...
                    jQuery('#videos-found .progress-bar').removeClass('no-anim');
                    jQuery('.menu-icon-post .wp-menu-name').append(' <span class="lvjm-update-posts"><span class="plugin-count"></span></span>');
                    jQuery('[data-id="cat_s_select"]').prop("disabled", true);
                    jQuery('[data-id="partner_select"]').prop("disabled", true);

                    //reset tooltips states
                    jQuery('[rel=tooltip]').tooltip('hide');

                    var checkedVideosCounter = this.savedCheckedVideosCounter = this.checkedVideosCounter;
                    var cat_s = this.selectedCat;
                    var kw_s = this.selectedKW;
                    var cat_wp = this.selectedWPCat;
                    var partner_id = this.selectedPartner;
                    var status = this.selectedPostsStatus;
                    var total_videos = this.checkedVideosCounter;
                    var method = this.searchFromFeed ? 'update' : 'create';

                    var feed_id = '';

                    var kw = 0;
                    if (kw_s != '') {
                        kw = 1;
                        cat_s = kw_s.split(' ').join(this.selectedPartnerObject.filters.search_sep).trim();
                        feed_id = cat_wp + '__' + partner_id + '__kw::' + cat_s;
                    } else {
                        feed_id = cat_wp + '__' + partner_id + '__' + cat_s;
                    }
                    this.videos.filter(function (video) {
                        return video.checked;
                    }).forEach(function (video) {
                        self.importingVideos = true;
                        self.$http.post(
                                LVJM_import_videos.ajax.url, {
                                    action: 'lvjm_import_video',
                                    nonce: LVJM_import_videos.ajax.nonce,
                                    cat_wp: cat_wp,
                                    cat_s: cat_s,
                                    feed_id: feed_id,
                                    kw: kw,
                                    method: method,
                                    partner_id: partner_id,
                                    status: status,
                                    video_infos: video
                                }, {
                                    emulateJSON: true
                                })
                            .then(function (response) {
                                if (response.body === -1) {
                                    console.error(response);
                                }
                                // success callback
                                video.grabbed = true;
                                video.checked = false;
                                // display post creation notice
                                jQuery('.update-posts .plugin-count').text('+ ' + (self.savedCheckedVideosCounter - self.checkedVideosCounter));

                            }, function (response) {
                                // error callback
                                console.error(response);
                            }).then(function () {

                                if (--checkedVideosCounter <= 0) {

                                    //update feed infos
                                    self.$http.post(
                                            LVJM_import_videos.ajax.url, {
                                                action: 'lvjm_update_feed',
                                                nonce: LVJM_import_videos.ajax.nonce,
                                                cat_s: cat_s,
                                                cat_wp: cat_wp,
                                                feed_id: feed_id,
                                                kw: kw,
                                                method: method,
                                                partner_id: partner_id,
                                                status: status,
                                                total_videos: total_videos
                                            }, {
                                                emulateJSON: true
                                            })
                                        .then(function (response) {
                                            // success callback
                                            if (response.body.feed) {
                                                var existingIndex = lodash.findIndex(this.data.feeds, function (f) {
                                                    return f.id == feed_id;
                                                });

                                                if (existingIndex >= 0) {
                                                    //update existing feed
                                                    this.$set(this.data.feeds, existingIndex, response.body.feed);
                                                } else {
                                                    //adding feed
                                                    this.data.feeds.push(response.body.feed);
                                                }
                                            }
                                        }, function (response) {
                                            // error callback
                                            console.error(response);
                                        }).then(function () {

                                            jQuery('#videos-found .progress-bar').addClass('no-anim');
                                            jQuery('#videos-found .progress').addClass('finished');

                                            var delay = setTimeout(function () {

                                                if (self.firstImport) {
                                                    jQuery('[data-id="cat_s_select"]').next('div').find("li.selected").addClass('disabled')
                                                        .children('a').attr('aria-disabled', 'true')
                                                        .children('span.text').append(' (Used in a Feed)');
                                                }
                                                jQuery('[data-id="cat_s_select"]').prop("disabled", false);
                                                jQuery('[data-id="partner_select"]').prop("disabled", false);

                                                jQuery('#videos-found .progress').removeClass('finished');
                                                jQuery('.update-posts').remove();


                                                self.importingVideos = false;
                                                self.firstImport = false;

                                            }, 2000);
                                        });
                                }
                            });
                    });
                },
                getPartnerCatName: function (partnerId, partnerCatId) {
                    var partner = lodash.find(this.data.partners, function (p) {
                        return p.id == partnerId
                    });
                    if (partner === undefined) return partnerCatId;
                    var partnerCat = lodash.find(partner.categories, function (c) {
                        return c.id == partnerCatId
                    });
                    return partnerCat !== undefined ? partnerCat.name : partnerCatId;
                },
                confirmFeedDeletion: function (feedId) {
                    this.deleteFeedId = feedId;
                    jQuery('#delete-feed-modal').modal('show');
                },
                deleteFeed: function () {
                    this.loading.deleteFeed = true;
                    
                    // Injected: support for All Straight Categories
                    if (this.selectedPartnerCats === 'all_straight') {
                        postData.multi_category_search = 1;
                    }

                    this.$http.post(
    
                            LVJM_import_videos.ajax.url, {
                                action: 'lvjm_delete_feed',
                                nonce: LVJM_import_videos.ajax.nonce,
                                feed_id: this.deleteFeedId
                            }, {
                                emulateJSON: true
                            })
                        .then((response) => {
                            // success callback
                            jQuery('#delete-feed-modal').modal('hide');
                            var self = this;
                            this.data.feeds = lodash.filter(this.data.feeds, function (f) {
                                return f.id != self.deleteFeedId;
                            });
                        }, (response) => {
                            // error callback
                            console.error(response);
                        }).then(() => {
                            this.loading.deleteFeed = false;
                            this.deleteFeedId = '';
                        });
                }
            },
            mounted: function () {
                this.loading.loadingData = true;
                var self = this;
                
                    // Injected: support for All Straight Categories
                    if (this.selectedPartnerCats === 'all_straight') {
                        postData.multi_category_search = 1;
                    }

                    this.$http.post(
    
                        LVJM_import_videos.ajax.url, {
                            action: 'lvjm_load_import_videos_data',
                            nonce: LVJM_import_videos.ajax.nonce
                        }, {
                            emulateJSON: true
                        })
                    .then((response) => {
                        // success callback
                        this.data = response.body;
                        switch (self.sortPartners) {
                            case 'popularity':
                                self.data.partners = lodash.orderBy(self.data.partners, ['popularity'], ['desc']);
                                break;
                            case 'alpha':
                                self.data.partners = lodash.orderBy(self.data.partners, ['id'], ['asc']);
                                break;
                        }
                        this.selectedPartner = this.data.partners[0].id;

                    }, (response) => {
                        // error callback
                        console.error(response);
                    }).then(() => {
                        this.loading.loadingData = false;
                        this.dataLoaded = true;
                        this.loadPartnerCats();
                    });

                this.resetSearch();

                // load the video embedder when video modal is shown
                jQuery('body').on('show.bs.modal', '.modal#video-preview-modal', function (e) {

                    self.$http.post(
                        LVJM_import_videos.ajax.url, {
                            action: 'lvjm_get_embed_and_actors',
                            nonce: LVJM_import_videos.ajax.nonce,
                            video_id: self.currentVideo.id
                        }, {
                            emulateJSON: true
                        })
                    .then((response) => {
                        if (! self.currentVideo.actors) {
                            self.currentVideo.actors = response.body.performer_name;
                        }
                        if ( ! self.currentVideoEmbed ) {
                            self.currentVideoEmbed = response.body.embed;
                        }
                        // success callback
                    }, (response) => {
                        // error callback
                        console.error(response);
                    }).then( function() {
                    });
                });

                //stop video when video modal is hidden
                jQuery('body').on('hidden.bs.modal', '.modal#video-preview-modal', function (e) {
                    self.currentVideoUrl = '';
                    self.currentVideoEmbed = '';
                    self.expandedThumb = '';
                });

                jQuery('body').on('shown.bs.tab', 'a[data-toggle="tab"]', function (e) {
                    if (jQuery(e.relatedTarget).attr('href') == '#current-video-data') {
                        self.currentVideoUrl = '';
                        self.currentVideoEmbed = '';
                        self.expandedThumb = '';
                    } else {
                        self.currentVideoUrl = self.currentVideo.video_url;
                        self.currentVideoEmbed = self.currentVideo.embed;
                    }
                });
            },
            watch: {
                selectedPartner: function (newPartnerId) {
                    this.videos = [];
                    this.videosHasBeenSearched = false;
                    if (!this.searchingVideos) {
                        //reset selectecat field
                        this.selectedCat = '';
                        //reset selectedKW field
                        this.selectedKW = '';
                        this.loadPartnerCats();
                    }
                },
                selectedCat: function (newCat, oldCat) {
                    if ('' != newCat) {
                        this.selectedKW = '';
                        this.videos = [];
                        this.videosHasBeenSearched = false;
                    }
                },
                selectedWPCat: function (newWpCat, oldWpCat) {
                    if ('+' == newWpCat) {
                        this.selectedWPCat = oldWpCat;
                        jQuery('#add-wp-cat-modal').modal('show');
                        jQuery('#cat_wp_select').selectpicker('val', oldWpCat);
                        this.newWpCategoryName = this.selectedPartnerCatName ? this.selectedPartnerCatName : this.selectedKW;
                    }
                },
                selectedKW: function (newKW) {
                    if ('' != newKW) {
                        jQuery('#cat_s_select').selectpicker('val', '');
                        this.selectedCat = '';
                        this.videos = [];
                        this.videosHasBeenSearched = false;
                    }
                }
            },
            directives: {
                img: {
                    inserted: function (el, arg) {
                        // Focus the element
                        var img = new Image();
                        img.src = arg.value;
                        img.onload = function () {
                            el.src = arg.value;
                            var thumbWidth = jQuery(el)[0].getBoundingClientRect().width;
                        }
                    }
                }
            },
            components: {
                'bootstrap-select': {
                    props: ['value', 'options'],
                    template: `
                        <select>
                            <slot></slot>
                        </select>
                    `,
                    mounted: function () {
                        var vm = this;
                        jQuery(this.$el).selectpicker(this.options).on('rendered.bs.select', function () {
                            vm.$emit('input', this.value);
                        });
                    },
                    destroyed: function () {
                        jQuery(this.$el).selectpicker('destroy');
                    }
                },
                'partner-cats-select': {
                    props: ['data', 'id', 'options', 'value'],
                    template: `
                        <select v-bind:id="id">
                            <option value="">- Select a category -</option>
                            <template v-for="cat in data">
                                <template v-if='cat.id == "optgroup"'>
                                    <optgroup v-bind:label="cat.name">
                                        <option v-for="subCat in cat.sub_cats" v-bind:value="subCat.id" v-bind:disabled="subCat.disabled">{{subCat.name}} <span v-if="subCat.disabled">(Used in a Feed)</span></option>
                                    </optgroup>
                                </template>
                                <template v-else>
                                    <option v-bind:value="cat.id" v-bind:disabled="cat.disabled">{{cat.name}} <span v-if="cat.disabled">(Used in a Feed)</span></option>
                                </template>
                            </template>
                        </select>
                    `,
                    mounted: function () {
                        var vm = this;
                        jQuery(this.$el).selectpicker(this.options).on('rendered.bs.select', function () {
                            vm.$emit('input', this.value);
                        });
                    },
                    destroyed: function () {
                        jQuery(this.$el).selectpicker('destroy');
                    }
                },
                'partner-options': {
                    props: ['data', 'selectedPartnerObject'],
                    data: function () {
                        return {
                            loading: {
                                'savingOptions': false
                            },
                            tags: ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p']
                        }
                    },
                    template: `
                        <form id="partner-options-form" method="post" action="" class="form-horizontal" role="form">
                        <template>
                            <div v-for="option in data" class="row">
                                <div v-if="tags.indexOf( option.type ) > -1" class="col-sm-12">
                                    <div v-if="option.type == 'p'"><p>{{option.desc}}</p></div>
                                    <div v-if="option.type == 'h1'"><h1>{{option.desc}}</h1></div>
                                    <div v-if="option.type == 'h2'"><h2>{{option.desc}}</h2></div>
                                    <div v-if="option.type == 'h3'"><h3>{{option.desc}}</h3></div>
                                    <div v-if="option.type == 'h4'"><h4>{{option.desc}}</h4></div>
                                    <div v-if="option.type == 'h5'"><h5>{{option.desc}}</h5></div>
                                    <div v-if="option.type == 'h6'"><h6>{{option.desc}}</h6></div>
                                </div>
                                <template v-else>
                                    <template v-if="option.type != 'checkbox'">
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">{{option.intitule + (option.required ? ' *':'')}}</label>
                                            <template v-if="option.type == 'checkbox'">
                                                ...
                                            </template>
                                            <template v-if="option.type == 'input'">
                                                <div class="col-sm-9"><input class="form-control" type="text" v-model="option.value"/>
                                                    <p v-if="option.desc" class="alert-info border-radius-4">{{option.desc}}</p>
                                                </div>
                                            </template>
                                            <template v-if="option.type == 'select'">
                                                <div class="col-sm-9">
                                                    <select class="form-control" type="text" v-model="option.value">
                                                        <option v-for="(option_value, option_key) in option.select_options" v-bind:value="option_key">{{option_value}}</option>
                                                    </select>
                                                    <p v-if="option.desc" class="alert-info border-radius-4">{{option.desc}}</p>
                                                </div>
                                            </template>
                                            <template v-if="option.type == 'textarea'">
                                                ...
                                            </template>
                                        </div>
                                    </template>
                                </template>
                            </div>
                            <div class="row">
                                <div class="col-xs-12 col-md-6 col-lg-4 col-md-push-3 col-md-push-4">
                                    <div class="form-group">
                                        <button v-on:click.prevent="saveOptions" id="partner-save-options" type="submit" class="btn btn-success btn-lg btn-block" v-bind:class="{disabled:this.loading.savingOptions}">
                                            <span v-show="this.loading.savingOptions"><i class="fa fa-spinner fa-pulse"></i> Saving Options</span>
                                            <span v-show="!this.loading.savingOptions">Save Options</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </form>
                    `,
                    methods: {
                        saveOptions: function () {
                            this.loading.savingOptions = true;
                            
                    // Injected: support for All Straight Categories
                    if (this.selectedPartnerCats === 'all_straight') {
                        postData.multi_category_search = 1;
                    }

                    this.$http.post(
    
                                    LVJM_import_videos.ajax.url, {
                                        action: 'lvjm_save_partner_options',
                                        nonce: LVJM_import_videos.ajax.nonce,
                                        partner_id: this.selectedPartnerObject.id,
                                        partner_options: this.selectedPartnerObject.options
                                    }, {
                                        emulateJSON: true
                                    })
                                .then((response) => {
                                    this.selectedPartnerObject.options.map( function( option ) {
                                        // Skip if the option is not site redirect
                                        if ( 'site' !== option.id ) {
                                            return;
                                        }
                                        // Skip if the site redirect option was empty.
                                        if ( '' === option.value ) {
                                            return;
                                        }
                                        // Skip if the new site redirect option is a good site (not empty),
                                        // display a success message.
                                        if ( '' !== response.body.site ) {
                                            option.value = 'Success! The whitelabel id is: ' + response.body.whitelabel_id;
                                            setTimeout(function() {
                                                option.value = response.body.site;
                                            }, 2000);
                                            return;
                                        }
                                        // Else, display error message during 2 seconds.
                                        option.value = 'This url is not a valid whitelabel site.';
                                        setTimeout(function() {
                                            option.value = '';
                                        }, 2000);
                                    });
                                    // success callback
                                    this.selectedPartnerObject.is_configured = response.body.is_configured;
                                }, (response) => {
                                    // error callback
                                    console.error(response);
                                }).then(() => {
                                    var self = this;
                                    self.loading.savingOptions = false;
                                    jQuery('#partner_select').selectpicker('refresh');
                                });
                        }
                    }
                },
                'feed': {
                    props: ['feed', 'wpCats', 'partnerCatName', 'smtIsLoading', 'deleteFeedId', 'partners', 'autoImportEnabled'],
                    data: function () {
                        return {
                            loading: {
                                'savingOptions': false,
                                'savingStatus': false,
                                'savingAutoImport': false,
                                'savingDeleteFeed': false,
                                'searchingVideo': false
                            },
                        }
                    },
                    template: `
                            <div class="col-xs-12 col-sm-6 col-md-4 col-lg-2 item">
                                <div class="block-white text-center">
                                    <div class="feed-id">{{feed.id}}</div>
                                    <i v-on:click.prevent="confirmFeedDeletion" v-bind:disabled="loading.savingOptions || this.smtIsLoading" class="fa fa-times text-danger delete-feed" aria-hidden="true" rel="tooltip" data-placement="top" data-original-title="Delete this feed"></i>
                                    <p>
                                        <img class="border-radius-4" v-bind:src="'https://res.cloudinary.com/themabiz/image/upload/wpscript/sources/' + feed.partner_id + '.jpg'"><br>
                                    <p>
                                    <p>
                                        <small>From</small> <span class="label label-default" v-html="cleanedPartnerCatName"></span>
                                        <br>
                                        <small>to</small>
                                        <span v-if="wpCatExists && wpCatObject" class="label label-success"><i class="fa fa-wordpress" aria-hidden="true"></i> {{wpCatObject.name}}</span>
                                        <span v-else class="label label-danger"><i class="fa fa-wordpress" aria-hidden="true"></i> Category removed</span>
                                    </p>
                                    <p>
                                        <small>Videos imported: <strong>{{feed.total_videos}}</strong></small>
                                        <br>
                                        <small>Last update: <strong>{{feed.last_update}}</strong> ({{feed.last_update_method}})</small>
                                        <br>
                                        <small>Last page crawled: <strong>{{feed.last_page_crawled ? feed.last_page_crawled : '-'}}</strong></small>
                                    </p>
                                    <template v-if="!(wpCatExists && wpCatObject)">
                                        <button class="btn" style="visibility:hidden">&nbsp;</button>
                                    </template>
                                    <template v-else>
                                        <template v-if="autoImportEnabled == 'on'">
                                            <div class="btn-group" role="group">
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-default dropdown-toggle" v-bind:disabled="loading.savingOptions || !partnerExists || this.smtIsLoading" data-toggle="dropdown" aria-expanded="false" rel="tooltip" data-placement="top" data-original-title="Change videos status when importing">
                                                        <span v-html="status"></span>
                                                    </button>
                                                    <ul class="dropdown-menu" role="menu">
                                                        <li><a href="#" v-on:click.prevent="changeStatus('publish')"><i class="fa fa-check text-success"></i> Import videos with <strong>Publish</strong> status</a></li>
                                                        <li><a href="#" v-on:click.prevent="changeStatus('draft')"><i class="fa fa-pencil text-danger"></i> Import videos with <strong>Draft</strong> status</a></li>
                                                    </ul>
                                                </div>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-default  dropdown-toggle" v-bind:disabled="loading.savingOptions || !partnerExists || this.smtIsLoading" data-toggle="dropdown" aria-expanded="false" rel="tooltip" data-placement="top" data-original-title="Enable/Disable auto-import">
                                                        <span v-html="autoImport"></span>
                                                    </button>
                                                    <ul class="dropdown-menu" role="menu">
                                                        <li><a href="#" v-on:click.prevent="toggleAutoImport(true)"><i class="fa fa-play text-success"></i> <strong>Enable</strong> auto import</a></li>
                                                        <li><a href="#" v-on:click.prevent="toggleAutoImport(false)"><i class="fa fa-pause text-danger"></i> <strong>Disabled</strong> auto import</a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-default dropdown-toggle" v-bind:disabled="loading.savingOptions || !partnerExists || this.smtIsLoading" data-toggle="dropdown" aria-expanded="false" rel="tooltip" data-placement="top" data-original-title="Change videos status when importing">
                                                    <span v-html="status"></span>
                                                </button>
                                                <ul class="dropdown-menu" role="menu">
                                                    <li><a href="#" v-on:click.prevent="changeStatus('publish')"><i class="fa fa-check text-success"></i> Import videos with <strong>Publish</strong> status</a></li>
                                                    <li><a href="#" v-on:click.prevent="changeStatus('draft')"><i class="fa fa-pencil text-danger"></i> Import videos with <strong>Draft</strong> status</a></li>
                                                </ul>
                                            </div>
                                        </template>
                                        <button v-on:click.prevent="searchVideos" class="btn btn-info" v-bind:disabled="loading.savingOptions || !partnerExists || this.smtIsLoading" rel="tooltip" data-placement="top" data-original-title="Search new videos"><i class="fa fa-search"></i></button>
                                    </template>
                                </div>
                            </div>
                    `,
                    computed: {
                        wpCatExists() {
                            return this.feed.wp_cat_state == 1;
                        },
                        partnerExists() {
                            var self = this;
                            return lodash.find(this.partners, function (p) {
                                return p.id == self.feed.partner_id
                            });
                        },
                        cleanedPartnerCatName() {
                            if (this.partnerCatName.indexOf('kw::') >= 0) {
                                return '<i class="fa fa-tag" aria-hidden="true"></i> ' + this.partnerCatName.replace('kw::', '');
                            }
                            return '<i class="fa fa-folder-open-o" aria-hidden="true"></i> ' + this.partnerCatName;
                        },
                        wpCatObject: function () {
                            var self = this;
                            return lodash.find(this.wpCats, function (c) {
                                return c.term_id == self.feed.wp_cat
                            });
                        },
                        status: function () {
                            if (this.loading.savingStatus) {
                                return '<i class="fa fa-spinner fa-pulse"></i>';
                            }
                            switch (this.feed.status) {
                                case 'publish':
                                    return '<i class="fa fa-check text-success"></i> <span class="caret"></span>';
                                case 'draft':
                                    return '<i class="fa fa-pencil text-danger"></i> <span class="caret"></span>';
                            }
                        },
                        autoImport: function () {
                            if (this.loading.savingAutoImport) {
                                return '<i class="fa fa-spinner fa-pulse"></i>';
                            }
                            if (true === this.feed.auto_import) {
                                return '<i class="fa fa-play text-success"></i> <span class="caret"></span>';
                            } else {
                                return '<i class="fa fa-pause text-danger"></i> <span class="caret"></span>';
                            }
                        }
                    },
                    methods: {
                        changeStatus: function (newValue) {
                            this.loading.savingOptions = this.loading.savingStatus = true;
                            
                    // Injected: support for All Straight Categories
                    if (this.selectedPartnerCats === 'all_straight') {
                        postData.multi_category_search = 1;
                    }

                    this.$http.post(
    
                                    LVJM_import_videos.ajax.url, {
                                        action: 'lvjm_change_feed_status',
                                        nonce: LVJM_import_videos.ajax.nonce,
                                        feed_id: this.feed.id,
                                        new_value: newValue
                                    }, {
                                        emulateJSON: true
                                    })
                                .then((response) => {
                                    if (response.body === true)
                                        this.feed.status = newValue;
                                }, (response) => {
                                    // error callback
                                    console.error(response);
                                }).then(() => {
                                    this.loading.savingOptions = this.loading.savingStatus = false;
                                });
                        },
                        toggleAutoImport: function (newValue) {
                            this.loading.savingOptions = this.loading.savingAutoImport = true;
                            
                    // Injected: support for All Straight Categories
                    if (this.selectedPartnerCats === 'all_straight') {
                        postData.multi_category_search = 1;
                    }

                    this.$http.post(
    
                                    LVJM_import_videos.ajax.url, {
                                        action: 'lvjm_toggle_feed_auto_import',
                                        nonce: LVJM_import_videos.ajax.nonce,
                                        feed_id: this.feed.id,
                                        new_value: newValue
                                    }, {
                                        emulateJSON: true
                                    })
                                .then((response) => {
                                    // success callback
                                    if (response.body === true) {
                                        this.feed.auto_import = newValue;
                                    }
                                }, (response) => {
                                    // error callback
                                    console.error(response);
                                }).then(() => {
                                    this.loading.savingOptions = this.loading.savingAutoImport = false;
                                });
                        },
                        confirmFeedDeletion: function () {
                            this.$emit('confirm-feed-deletion', this.feed.id);
                        },
                        searchVideos: function () {
                            this.$emit('search-videos', 'update', this.feed.id);
                        }
                    }
                },

            }
        });
    }
};
