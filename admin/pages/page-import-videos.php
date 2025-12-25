<?php

error_log('[WPS-LiveJasmin] Import videos page accessed');
/**
 * Admin Import Page plugin file.
 *
 * @package LIVEJASMIN\Admin\Pages
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Callback for the plugin Import page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lvjm_import_videos_page() {
	?>
	<div id="wp-script">
		<div class="content-tabs" id="import-videos">
			<?php WPSCORE()->display_logo(); ?>
			<?php WPSCORE()->display_tabs(); ?>
			<div class="tab-content">
				<div class="tab-pane fade in active" id="import-videos">
					<div>
						<ul class="list-inline">
							<li class="active"><a href="admin.php?page=lvjm-import-videos"><i class="fa fa-cloud-download"></i> <?php esc_html_e( 'Import videos', 'lvjm_lang' ); ?></a></li>
							<li>|</li>
							<li><a href="admin.php?page=lvjm-options"><i class="fa fa-wrench"></i> <?php esc_html_e( 'Options', 'lvjm_lang' ); ?></a></li>
						</ul>
					</div>
					<div v-cloak id="search-top" class="padding-top-15">
						<div class="row text-center v-cloak--block">
							<div class="col-xs-12 loading"><p><i class="fa fa-cog fa-spin fa-2x fa-fw" aria-hidden="true"></i><br><?php esc_html_e( 'Loading Page', 'lvjm_lang' ); ?>...</span></p></div>
						</div>
						<div class="v-cloak--hidden">
							<!--**************-->
							<!-- LOADING DATA -->
							<!--**************-->
							<template v-if="loading.loadingData">
								<div class="row text-center">
									<div class="col-xs-12 loading"><p><i class="fa fa-cog fa-spin-reverse fa-2x fa-fw" aria-hidden="true"></i><br><?php esc_html_e( 'Loading Data', 'lvjm_lang' ); ?>...</span></p></div>
								</div>
							</template>
							<transition name="fade">
								<div v-if="dataLoaded">
									<!-- search videos block -->
									<div class="row">
										<div class="col-xs-12">
											<h3 v-if="searchFromFeed && searchingVideos" class="text-center">
												<?php esc_html_e( 'Searching new videos from this Saved Feed', 'lvjm_lang' ); ?>: <img class="border-radius-4" v-bind:src="'https://res.cloudinary.com/themabiz/image/upload/wpscript/sources/' + selectedPartnerObject.id + '.jpg'" v-bind:alt="selectedPartnerObject.name"> / {{selectedKW != '' && selectedKW != undefined ? 'Keyword "' + selectedKW + '"':'Category "' + selectedPartnerCatName + '"'}}
												<br><br>
												<i class="fa fa-spinner fa-pulse fa-3x" aria-hidden="true"></i>
											</h3>
											<h3 v-if="!searchFromFeed">
												<i class="fa fa-search" aria-hidden="true"></i> <?php esc_html_e( 'Search videos to import', 'lvjm_lang' ); ?>
												<button v-if="selectedPartnerObject.is_configured" class="btn btn-default pull-right" data-toggle="modal" data-target="#partner-config-modal" rel="tooltip" data-placement="top" v-bind:data-original-title="'Add your ' + selectedPartnerObject.name + ' ID to track conversions, see their informations and more...'" v-bind:disabled="searchingVideos"><i class="fa fa-cog" aria-hidden="true"></i> <?php esc_html_e( 'Configure', 'lvjm_lang' ); ?> <strong>{{selectedPartnerObject.name}}</strong></button>
											</h3>
											<div v-show="!searchFromFeed" id="block-search">
												<div class="sponsor-not-configured" v-show="!selectedPartnerObject.is_configured">
													<div class="alert alert-info no-margin">
														<a href="#" data-toggle="modal" data-target="#partner-config-modal" rel="tooltip" data-placement="top" v-bind:data-original-title="'Add your ' + selectedPartnerObject.name + ' ID to track conversions, see their informations and more...'" v-bind:disabled="searchingVideos"><i class="fa fa-cog" aria-hidden="true"></i> <?php esc_html_e( 'Configure', 'lvjm_lang' ); ?> <strong>{{selectedPartnerObject.name}}</strong></a> <?php esc_html_e( 'to use it', 'lvjm_lang' ); ?>.
													</div>
												</div>
												<div id="step-1">
													<div class="modal fade partner-config" id="partner-config-modal" tabindex="-1" role="dialog" aria-hidden="true">
														<div class="modal-dialog modal-lg">
															<div class="modal-content">
																<div class="modal-body">
																	<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
																	<div v-show="!partnerConfigLoading" v-html="selectedPartnerConfig"></div>
																	<div class="tab-pane active">
																		<div class="row margin-bottom-20">
																			<div class="col-xs-12">
																				<a style="text-align: right;" v-bind:href="'https://www.wp-script.com/partners/' + selectedPartnerObject.id" target="_blank"><img class="border-radius-4" v-bind:src="'https://res.cloudinary.com/themabiz/image/upload/wpscript/sources/' + selectedPartnerObject.id + '.jpg'" v-bind:alt="selectedPartnerObject.name"/></a>
																				<?php /* <a class="btn btn-info btn-large create-account" v-bind:href="'https://www.wp-script.com/register/' + selectedPartnerObject.id" v-bind:title="'<?php esc_html_e( 'Click here to get an affiliate ID from', 'lvjm_lang' ); ?>' + selectedPartnerObject.name" target="_blank"><?php esc_html_e( 'Click here to get an affiliate ID from', 'lvjm_lang' ); ?> {{selectedPartnerObject.name}} <i class="fa fa-external-link" aria-hidden="true"></i></a> */ ?>
																				<ul class="nav nav-tabs padding-top-15" role="tablist">
																					<li v-if="selectedPartnerObject.options" class="active"><a href="#partner-configuration" role="tab" data-toggle="tab"><i class="fa fa-cog" aria-hidden="true"></i> <?php esc_html_e( 'Configuration', 'lvjm_lang' ); ?></a></li>
																					<li v-bind:class="{active:!selectedPartnerObject.options}"><a href="#partner-informations" role="tab" data-toggle="tab"><i class="fa fa-info-circle" aria-hidden="true"></i> <?php esc_html_e( 'Informations', 'lvjm_lang' ); ?></a></li>
																				</ul>
																				<div class="tab-content">
																					<div v-if="selectedPartnerObject.options" role="tabpanel" class="tab-pane active" id="partner-configuration">
																						<div class="alert alert-info">LiveJasmin is managed by AWEmpire (affiliate program). <a href="https://www.wp-script.com/login/awempire/" target="_blank">Login</a> or <a href="https://www.wp-script.com/register/awempire/" target="_blank">Sign up</a> to AWEmpire to get your PSID and Access Key.<br><a href="https://www.youtube.com/watch?v=rejHm9_FdsQ&t=12s" target="_blank">Click here</a> to watch in video how to find these parameters.</div>
																						<partner-options v-bind:data="selectedPartnerObject.options" v-bind:selected-partner-object="selectedPartnerObject" ></partner-options>
																					</div>
																					<div role="tabpanel" class="tab-pane" v-bind:class="{active:!selectedPartnerObject.options}" id="partner-informations">
																						<div class="row padding-bottom-20 about">
																							<div class="col-xs-12 col-md-8">
																								<h4><?php esc_html_e( 'Description', 'lvjm_lang' ); ?></h4>
																								<p>{{selectedPartnerObject.description}}</p>
																								<br />
																								<h4><?php esc_html_e( 'Niches', 'lvjm_lang' ); ?></h4>
																								<template v-for="cat in selectedPartnerObject.categories">
																									<template v-if="cat.sub_cats">
																										<h5>{{cat.name}}</h5>
																										<template v-for="sub_cat in cat.sub_cats">
																											<span class="label label-default">{{sub_cat.name}}</span>{{String.fromCharCode(13)}}
																										</template>
																									</template>
																									<span v-else class="label label-default">{{cat.name}}</span>{{String.fromCharCode(13)}}
																								</template>
																							</div>
																							<div class="col-xs-12 col-md-4">
																								<h4><?php esc_html_e( 'Videos Details', 'lvjm_lang' ); ?></h4>
																								<ul>
																									<li><strong><?php esc_html_e( 'Language(s)', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.language.join(', ')}}</li>
																									<li><strong><?php esc_html_e( 'HTTPS ready', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.https === true ? '<?php esc_html_e( 'Yes', 'lvjm_lang' ); ?>':'<?php esc_html_e( 'No', 'lvjm_lang' ); ?>'}}</li>
																									<li><strong><?php esc_html_e( 'Mobile ready', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.mobile_ready === true ? '<?php esc_html_e( 'Yes', 'lvjm_lang' ); ?>':'<?php esc_html_e( 'No', 'lvjm_lang' ); ?>'}}</li>
																									<li><strong><?php esc_html_e( 'Mobile Redirection', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.mobile_redirect === true ? '<?php esc_html_e( 'Yes', 'lvjm_lang' ); ?>':'<?php esc_html_e( 'No', 'lvjm_lang' ); ?>'}}</li>
																									<li><strong><?php esc_html_e( 'Multiple Thumbnails', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.multithumbs === true ? '<?php esc_html_e( 'Yes', 'lvjm_lang' ); ?>':'<?php esc_html_e( 'No', 'lvjm_lang' ); ?>'}}</li>
																									<li><strong><?php esc_html_e( 'Trailer', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.trailer === true ? '<?php esc_html_e( 'Yes', 'lvjm_lang' ); ?>':'<?php esc_html_e( 'No', 'lvjm_lang' ); ?>'}}</li>
																									<li><strong><?php esc_html_e( 'Orientation(s)', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.orientation.join(', ')}}</li>
																									<li><strong><?php esc_html_e( 'Models List', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.orientation.actors === true ? '<?php esc_html_e( 'Yes', 'lvjm_lang' ); ?>':'<?php esc_html_e( 'No', 'lvjm_lang' ); ?>'}}</li>
																								</ul>
																								<h4><?php esc_html_e( 'Payment Details', 'lvjm_lang' ); ?></h4>
																								<ul>
																									<li><strong><?php esc_html_e( 'Devise(s)', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.devise.join(', ')}}</li>
																									<li><strong><?php esc_html_e( 'Payment(s)', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.payment.join(', ')}}</li>
																									<li><strong><?php esc_html_e( 'Program(s)', 'lvjm_lang' ); ?></strong>: {{selectedPartnerObject.filters.program.join(', ')}}</li>
																								</ul>
																							</div>
																						</div>
																					</div>
																				</div>
																			</div>
																		</div>
																	</div>
																	<div class="clearfix"></div>
																</div>
															</div>
															<!-- /.modal-content -->
														</div>
														<!-- /.modal-dialog -->
													</div>
													<!-- /.modal -->
													<div class="block-white block-white-first sponsor-configured" v-show="selectedPartnerObject.is_configured">
														<div class="form-inline">
															<span class="step">1</span>
															<label for="partner_select"><?php esc_html_e( 'Select a niche from', 'lvjm_lang' ); ?> <img class="border-radius-4" v-bind:src="'https://res.cloudinary.com/themabiz/image/upload/wpscript/sources/' + selectedPartnerObject.id + '.jpg'" v-bind:alt="selectedPartnerObject.name"> </label>
															<i v-show="partnerCatsLoading" class="fa fa-spinner fa-pulse"></i>
															<div v-show="!partnerCatsLoading" style="display:inline;">
																<partner-cats-select v-model="selectedCat" id="cat_s_select" v-bind:data="partnerCats" v-bind:options="{'width':'fit', 'size':'20', 'liveSearch':true}"></partner-cats-select>
															</div>
															<span id="kw-search" v-show="selectedPartnerObject.filters.search_by == 'keyword'">
															<strong>- <?php esc_html_e( 'OR', 'lvjm_lang' ); ?> -</strong> <?php esc_html_e( 'Enter some keywords', 'lvjm_lang' ); ?> <input v-model="selectedKW" v-bind:disabled="searchingVideos" v-on:keyup.enter.prevent="searchVideos('create')" id="kw_s" type="text" placeholder="<?php esc_html_e( 'eg. ebony lesbian', 'lvjm_lang' ); ?>" name="kw_s" class="form-control" style="width:250px;">
															</span>
											<span id="performer-search" style="margin-left:8px;">
												<label for="performer_s" class="sr-only"><?php esc_html_e( 'Performer', 'lvjm_lang' ); ?></label>
												<input type="text" v-model="selectedPerformer" placeholder="<?php esc_attr_e( 'Performer (optional)', 'lvjm_lang' ); ?>" id="performer_s" name="performer_s" class="form-control" style="width:220px;">
											</span>

														</div>
													</div>
												</div>
												<div id="step-2" class="block-white sponsor-configured block-white-last" v-show="selectedPartnerObject.is_configured">
													<span class="step">2</span>
													<span v-show="videosHasBeenSearched">
														<button class="btn btn-default" disabled><i class="fa fa-check" aria-hidden="true"></i> <?php esc_html_e( 'Search done!', 'lvjm_lang' ); ?></button>
													</span>
													<button v-show="!searchingVideos && !videosHasBeenSearched" v-on:click.prevent="searchVideos('create')" class="btn btn-info" v-bind:class="searchBtnClass" rel="tooltip" data-placement="top" v-bind:data-original-title="searchButtonTooltip"><i class="fa fa-search" aria-hidden="true"></i> <?php esc_html_e( 'Search videos', 'lvjm_lang' ); ?></button>
													<button v-show="searchingVideos" disabled="disabled" class="btn btn-info"><i class="fa fa-spinner fa-pulse" aria-hidden="true"></i> <?php esc_html_e( 'Searching videos...', 'lvjm_lang' ); ?></button>
													<?php /* translators: %s: number of videos in the search results */ ?>
													<small><i class="fa fa-info-circle" aria-hidden="true"></i> <?php printf( esc_html__( 'Each search displays up to %s unique videos at a time and excludes any videos already imported.', 'lvjm_lang' ), '{{data.videosLimit}}' ); ?></small>
												</div>
											</div>
										</div>
									</div>
									<!-- / search videos block -->
									<!-- results success block -->
									<div class="row">
										<div class="col-xs-12" v-show="videosCounter <= 0 && videosHasBeenSearched">                                        
											<div v-if="videosSearchedErrors.code" class="alert alert-danger margin-top-10 text-center alert-dismissible" role="alert">
												<button type="button" class="close" v-on:click.prevent="resetSearch" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
												<p><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <strong>{{videosSearchedErrors.code}}</strong><br>{{videosSearchedErrors.message}}<br>-<br>{{videosSearchedErrors.solution}}</p>
												<p>
													<button class="btn btn-danger margin-top-10" v-on:click.prevent="resetSearch"><?php esc_html_e( 'Close', 'lvjm_lang' ); ?></button>
												</p>
											</div>
											<div v-else class="alert alert-info margin-top-10 text-center alert-dismissible" role="alert">
												<button type="button" class="close" v-on:click.prevent="resetSearch" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
												<p><i class="fa fa-meh-o" aria-hidden="true"></i> <strong><?php esc_html_e( 'No video found', 'lvjm_lang' ); ?></strong><br>
													<?php esc_html_e( 'All videos may have been already imported for the current search or via other imports with', 'lvjm_lang' ); ?> {{selectedPartnerObject.name}}<br>
													- <?php esc_html_e( 'OR', 'lvjm_lang' ); ?> -<br>
													<?php esc_html_e( 'There is unfortunately no video for this search', 'lvjm_lang' ); ?>
												</p>
												<p>
													<button class="btn btn-default margin-top-10" data-toggle="modal" data-target="#search-details-modal"><?php esc_html_e( 'Details', 'lvjm_lang' ); ?></button>
													<button class="btn btn-info margin-top-10" v-on:click.prevent="resetSearch"><?php esc_html_e( 'Close', 'lvjm_lang' ); ?></button>
												</p>
											</div>
										</div>
										<transition name="fade">
											<div v-show="videosCounter > 0" id="videos-found" class="col-xs-12 margin-top-10">
												<div id="sticky-space" class="col-xs-12"></div>
												<div id="videos-found-header" class="col-xs-12">
													<h3><i class="fa" v-bind:class="[displayType == 'cards' ? 'fa-th' : 'fa-list-ul']"></i> 
														<?php esc_html_e( 'Search results', 'lvjm_lang' ); ?> 
														<template v-if="searchFromFeed">
															: {{videosCounter}} <?php esc_html_e( 'new videos found with', 'lvjm_lang' ); ?> <img class="border-radius-4" v-bind:src="'https://res.cloudinary.com/themabiz/image/upload/wpscript/sources/' + selectedPartnerObject.id + '.jpg'" v-bind:alt="selectedPartnerObject.name"> / {{selectedKW != '' && selectedKW != undefined ? 'Keyword "' + selectedKW + '"':'Category "' + selectedPartnerCatName + '"'}}
														</template>
														<button class="btn btn-link btn-sm" data-toggle="modal" data-target="#search-details-modal"><?php esc_html_e( 'See details', 'lvjm_lang' ); ?></button>
													</h3>
													<div id="videos-found-header-block" class="margin-bottom-10">
														<div class="form-inline">
															<div id="videos-found-header-left" class="pull-left">
																<button v-bind:disabled="importingVideos" v-on:click.prevent="toogleAllVideos" class="btn btn-default" id="bulk-checked" rel="0"><i class="fa" v-bind:class="[allVideosChecked ? 'fa-check-square-o':'fa-square-o']"></i> <span v-if="!allVideosChecked"><?php esc_html_e( 'Check all videos', 'lvjm_lang' ); ?></span><span v-else><?php esc_html_e( 'Uncheck all videos', 'lvjm_lang' ); ?></span></button>
																<span v-show="!firstImport">
																	<button  v-on:click.prevent="importVideos" v-bind:class="importBtnClass" v-bind:disabled="importBtnClass == 'disabled'"  type="submit" class="btn btn-success" rel="tooltip" data-placement="top" v-bind:data-original-title="importButtonTooltip">
																		<span v-if="importingVideos">
																			<i class="fa fa-spinner fa-pulse"></i> {{savedCheckedVideosCounter - checkedVideosCounter}}/{{savedCheckedVideosCounter}} <?php esc_html_e( 'videos imported in', 'lvjm_lang' ); ?> {{selectedWPCatName}}
																		</span>
																		<span v-else>
																			<i class="fa fa-cloud-download"></i> <?php esc_html_e( 'Import', 'lvjm_lang' ); ?> {{checkedVideosCounter}} <?php esc_html_e( 'videos in', 'lvjm_lang' ); ?> {{selectedWPCatName}}
																		</span>
																	</button>
																	<button v-bind:disabled="importingVideos" class="btn btn-info" v-on:click.prevent="searchVideos('update', updatingFeedId)" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Hide current imported videos and search new videos', 'lvjm_lang' ); ?>"><i class="fa fa-refresh" aria-hidden="true"></i> <?php esc_html_e( 'Refresh search results', 'lvjm_lang' ); ?></button>
																</span>
																<span v-show="firstImport">
																	<bootstrap-select id="cat_wp_select" v-model="selectedWPCat" v-bind:options="{'width':'fit', 'size':'20', 'liveSearch':true}">
																		<option value="0">- <?php esc_html_e( 'Select a WordPress category', 'lvjm_lang' ); ?> -</option>
																		<option data-divider="true"></option>
																		<option value="+"><strong>+ <?php esc_html_e( 'Add a new category', 'lvjm_lang' ); ?></strong></option>
																		<option data-divider="true"></option>
																		<option v-for="(WPCat, index) in data.WPCats" v-bind:value="WPCat.term_id" v-bind:key="WPCat.term_id">
																			{{WPCat.name}}
																		</option>
																	</bootstrap-select>
																	<button v-on:click.prevent="importVideos" v-bind:class="importBtnClass" type="submit" class="btn btn-success" rel="tooltip" data-placement="top" v-bind:data-original-title="importButtonTooltip">
																		<span v-if="importingVideos">
																			<i class="fa fa-spinner fa-pulse"></i> {{savedCheckedVideosCounter - checkedVideosCounter}}/{{savedCheckedVideosCounter}} <?php esc_html_e( 'videos imported in', 'lvjm_lang' ); ?> {{selectedWPCatName}}
																		</span>
																		<span v-else>
																			<i class="fa fa-cloud-download"></i> <?php esc_html_e( 'Import', 'lvjm_lang' ); ?> {{checkedVideosCounter}} <?php esc_html_e( 'videos in', 'lvjm_lang' ); ?> {{selectedWPCatName}}
																		</span>
																	</button>
																</span>
															</div>
															<div class="pull-right">
																<div id="display-type" class="btn-group">
																	<button class="btn btn-default" v-bind:class="{'active':displayType == 'cards'}" v-on:click="updateDisplayType('cards')" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Display search results as cards', 'lvjm_lang' ); ?>"><i class="fa fa-th" aria-hidden="true"></i></button>
																	<button class="btn btn-default" v-bind:class="{'active':displayType == 'lists'}" v-on:click="updateDisplayType('lists')" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Display search results as a list', 'lvjm_lang' ); ?>"><i class="fa fa-list-ul" aria-hidden="true"></i></button>
																</div>
																<button v-show="videosHasBeenSearched" v-on:click.prevent="resetSearch" v-bind:disabled="importingVideos" class="btn btn-danger" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Close search results and make a new search', 'lvjm_lang' ); ?>"><span class="fa fa-times" aria-hidden="true"></span></button>
															</div>
														</div>
														<div class="clearfix"></div>
													</div>
													<div class="progress">
														<div class="progress-bar progress-bar-success" role="progressbar" v-bind:aria-valuenow="importProgress" aria-valuemin="0" aria-valuemax="100" v-bind:style="'width:' + importProgress + '%;'">
														<span><i aria-hidden="true" class="fa fa-check"></i> <?php esc_html_e( 'Import done!', 'lvjm_lang' ); ?></span>
														</div>
													</div>
													<div v-if="!selectedPartnerObject.filters.https && siteIsHttps" class="row margin-top-0 margin-bottom-10">
														<div class="col-xs-12">
															<div class="alert alert-danger text-center margin-bottom-0" role="alert">
																<p><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <strong>{{selectedPartnerObject.name}} <?php esc_html_e( 'Embed Code does NOT work with SSL (HTTPS://)', 'lvjm_lang' ); ?></strong></p>
															</div>
														</div>
													</div>
												</div>
												<div id="videos" class="row" v-bind:class="displayType">
													<template v-if="displayType == 'cards'">
														<div v-for="(video, index) in videos" class="col-xs-12 col-sm-6 col-md-3 col-lg-2 item-cards" v-bind:key="video.id">
															<div class="video" v-bind:class="{'grabbed': video.grabbed, 'checked': video.checked}" >
																<div class="video-img" v-on:click.prevent="setCurrentVideo(video, index)">
																	<img class="img-responsive" src="<?php echo esc_html( LVJM_URL ); ?>admin/assets/img/loading-thumb.gif" v-img="video.thumb_url" data-toggle="modal" data-target="#video-preview-modal" v-bind:alt="video.title" />
																	<div class="video-data" data-toggle="modal" data-target="#video-preview-modal">
																		<span class="video-duration"><i class="fa fa-clock-o" aria-hidden="true"></i> <small>{{video.duration | timeFormat}}</small></span>
																		<span v-if="video.thumbs_urls != ''" class="video-has-thumbs"> <small><i class="fa fa-th-large" aria-hidden="true"></i> {{video.thumbs_urls.length}}</small></span>
																		<span v-if="video.trailer_url != ''" class="video-has-trailer"> <small><i class="fa fa-file-video-o" aria-hidden="true"></i> 1</small></span>
																	</div>
																</div>
																<h4>{{video.title}}</h4>
																<div class="text-center" v-if="!video.grabbed">
																	<div class="btn-group">
																		<div class="btn-group">
																			<button v-on:click.prevent="toggleVideo(index, 'list')" class="btn text-center video-check" v-bind:class="[video.checked ? 'btn-success':'btn-default']" v-bind:disabled="loading.removingVideo" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Check to import this video', 'lvjm_lang' ); ?>"><i class="fa-lg" v-bind:class="[video.checked ? 'fa fa-check-square-o':'fa fa-square-o']" aria-hidden="true"></i></button>
																		</div>
																		<div class="btn-group">
																			<button v-on:click.prevent="setCurrentVideo(video, index)" type="button" class="btn btn-default text-center video-preview"  v-bind:disabled="loading.removingVideo" data-toggle="modal" data-target="#video-preview-modal" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Edit this video', 'lvjm_lang' ); ?>">
																				<i class="fa fa-pencil" aria-hidden="true"></i>
																			</button>
																		</div>
																	</div>
																	<button v-on:click.prevent="confirmVideoDeletion(video, index)" class="btn btn-default"  v-bind:disabled="loading.removingVideo" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Remove this video', 'lvjm_lang' ); ?>">
																		<i class="fa text-danger" v-bind:class="[video.loading.removing ? 'fa-spinner fa-pulse' : 'fa-trash']" aria-hidden="true"></i>
																	</button>
																</div>
																<button v-else class="btn text-center btn-block disabled"><?php esc_html_e( 'Video imported', 'lvjm_lang' ); ?></button>
															</div>
														</div>
													</template>
													<template v-else>
														<div class="col-xs-12">
															<div class="panel panel-default margin-bottom-10">
																<table class="table table-striped table-hover table-bordered">
																	<tr>
																		<th width="35"></th>
																		<th width="100"><?php esc_html_e( 'Thumb', 'lvjm_lang' ); ?></th>
																		<th><?php esc_html_e( 'Title', 'lvjm_lang' ); ?></th>
																		<th><?php esc_html_e( 'Description', 'lvjm_lang' ); ?></th>
																		<th><?php esc_html_e( 'Tags and Models', 'lvjm_lang' ); ?></th>
																		<th width="100" class="text-center">Actions</th>
																	</tr>
																	<tr v-for="(video, index) in videos" v-bind:key="video.id" class="item-list" v-bind:class="{'success':video.checked, 'grabbed':video.grabbed}">
																		<td v-if="!video.grabbed" class="item-list-toggle" width="35" v-on:click.prevent="toggleVideo(index, 'list')">
																			<i class="fa-lg" v-bind:class="[video.checked ? 'fa fa-check-square-o text-success':'fa fa-square-o']" aria-hidden="true"></i>
																		</td>
																		<td v-else class="item-list-toggle" width="35"></td>
																		<td width="100">
																			<img v-if="!video.grabbed" width="100" v-on:click.prevent="setCurrentVideo(video, index)" class="pointer" src="<?php echo 'lvjm_lang'; ?>admin/assets/img/loading-thumb.gif" v-img="video.thumb_url" data-toggle="modal" data-target="#video-preview-modal" v-bind:alt="video.title" />
																			<img v-else width="100"  src="<?php echo 'lvjm_lang'; ?>admin/assets/img/loading-thumb.gif" v-img="video.thumb_url" v-bind:alt="video.title" />
																		</td>
																		<template v-if="video.grabbed">
																			<td colspan="3" class="video-td-imported"><?php esc_html_e( 'Video imported', 'lvjm_lang' ); ?></td>
																		</template>
																		<template v-else>
																			<td>
																				<div class="margin-bottom-5"><input type="text" name="" v-model="video.title" v-bind:disabled="video.grabbed" class="form-control" placeholder="<?php esc_html_e( 'Title', 'lvjm_lang' ); ?>..."></div>
																				<template v-if="video.duration"><i class="fa fa-clock-o" aria-hidden="true"></i> <small>{{video.duration | timeFormat}}</small></template>
																				<template v-if="video.thumbs_urls != ''"> | <i class="fa fa-th-large" aria-hidden="true"></i> <small>{{video.thumbs_urls.length}}</small></template>
																				<template v-if="video.trailer_url != ''"> | <i class="fa fa-file-video-o" aria-hidden="true"></i> <small>1</small></template>
																			</td>
																			<td>
																				<textarea placeholder="<?php esc_html_e( 'Description', 'lvjm_lang' ); ?>..." name="" v-model="video.desc" v-bind:disabled="video.grabbed" class="form-control"></textarea>
																			</td>
																			<td>
																				<div class="input-group margin-bottom-8">
																					<span class="input-group-addon" id="actors"><i class="fa fa-users" aria-hidden="true"></i> <small><?php esc_html_e( 'Actors', 'lvjm_lang' ); ?></small></span>
																					<input type="text" name="actors" class="form-control" v-model="video.actors" v-bind:disabled="video.grabbed" placeholder="insert models separated by a comma" aria-describedby="actors">
																				</div>
																				<div class="input-group">
																					<span class="input-group-addon" id="tags"><i class="fa fa-tags" aria-hidden="true"></i> <small><?php esc_html_e( 'Tags', 'lvjm_lang' ); ?></small></span>
																					<input type="text" name="tags" class="form-control" v-model="video.tags" v-bind:disabled="video.grabbed" placeholder="insert tags separated by a comma" aria-describedby="tags">
																				</div>
																			</td>
																		</template>
																		<td class="text-center">
																			<button v-on:click.prevent="setCurrentVideo(video, index)" type="button" class="btn btn-default text-center video-preview"  v-bind:disabled="loading.removingVideo" data-toggle="modal" data-target="#video-preview-modal" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Edit this video', 'lvjm_lang' ); ?>">
																				<i class="fa fa-pencil" aria-hidden="true"></i>
																			</button>
																			<button v-on:click.prevent="removeVideo(video, index)" class="btn btn-default"  v-bind:disabled="loading.removingVideo" rel="tooltip" data-placement="top" data-original-title="<?php esc_html_e( 'Remove this video', 'lvjm_lang' ); ?>">
																				<i class="fa text-danger" v-bind:class="[video.loading.removing ? 'fa-spinner fa-pulse' : 'fa-trash']" aria-hidden="true"></i>
																			</button>
																		</td>
																	</tr>
																</table>
															</div>
														</div>
													</template>
												</div>
												<div class="clear"></div>
												<!-- Create WP Cat Modal -->
												<div class="modal fade" id="add-wp-cat-modal">
												<div class="modal-dialog" role="document">
													<div class="modal-content">
													<div class="modal-header">
													<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only"><?php esc_html_e( 'Close', 'lvjm_lang' ); ?></span></button>
													<h4 class="modal-title"><?php esc_html_e( 'Add a new WordPress Category', 'lvjm_lang' ); ?></h4>
													</div>
													<div class="modal-body">
														<div class="row">
														<div class="col-xs-12 margin-bottom-5">
															<div class="input-group">
																<span class="input-group-addon" id="new_category_name"><?php esc_html_e( 'Name', 'lvjm_lang' ); ?></span>
																<input type="text" name="new_category_name" class="form-control" v-model="newWpCategoryName" placeholder="New category name" aria-describedby="new category name" v-bind:class="{'disabled': !newWpCategoryName || addingNewWpCategory}" v-bind:disabled='!newWpCategoryName || addingNewWpCategory' v-on:keyup.enter.prevent="addNewWpCategory">
															</div>
														</div>
														</div>
													</div>
													<div class="modal-footer">
														<div class="row">
														<div class="col-xs-12">
															<button class="btn btn-default" data-dismiss="modal">Cancel</button>
															<button class="btn btn-primary" v-bind:class="{'disabled': !newWpCategoryName || addingNewWpCategory}" v-bind:disabled='!newWpCategoryName || addingNewWpCategory' v-on:click="addNewWpCategory">
															<template v-if="addingNewWpCategory">
																<i class="fa fa-spinner fa-pulse"></i> <?php esc_html_e( 'Adding', 'lvjm_lang' ); ?> {{newWpCategoryName}}
															</template>
															<template v-else>
																<?php esc_html_e( 'Add New Category', 'lvjm_lang' ); ?>
															</template>
															</button>
														</div>
														</div>
													</div>
													</div>
												</div>
												</div>
												<!-- /Create WP Cat Modal -->
												<!-- Video Modal -->
												<div class="modal fade" id="video-preview-modal" tabindex="-1" role="dialog">
													<div class="modal-dialog">
														<div class="modal-content">
															<div class="modal-header">
																<div class="row">
																	<div class="col-xs-11">
																	<input type="text" name="" v-model="currentVideo.title" v-bind:disabled="currentVideo.grabbed" class="form-control" placeholder="<?php esc_html_e( 'Title', 'lvjm_lang' ); ?>...">
																	</div>
																	<div class="col-xs-1">
																	<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
																	</div>
																</div>
															</div>
															<div class="modal-body" v-bind:key="currentVideo.id">
																<div class="row">
																	<div class="col-xs-12">
																		<ul class="nav nav-tabs padding-top-15" role="tablist">
																			<li class="active"><a href="#current-video-data" @click="setVideoTab('data')" id="tab-video-data" role="tab" data-toggle="tab"><i class="fa fa-youtube-play" aria-hidden="true"></i> <?php esc_html_e( 'Video data', 'lvjm_lang' ); ?></a></li>
																			<li v-if="currentVideo.thumbs_urls != ''"><a href="#current-video-thumbs" @click="setVideoTab('thumbs')" id="tab-video-thumbs" role="tab" data-toggle="tab"><i class="fa fa-th-large" aria-hidden="true"></i></span> <?php esc_html_e( 'Thumbnails', 'lvjm_lang' ); ?></a></li>
																			<li v-if="currentVideo.trailer_url != ''"><a href="#current-video-trailer" @click="setVideoTab('trailer')" id="tab-video-trailer" role="tab" data-toggle="tab"><i class="fa fa-file-video-o" aria-hidden="true"></i></span> <?php esc_html_e( 'Trailer', 'lvjm_lang' ); ?></a></li>
																		</ul>
																		<div class="tab-content">
																			<div role="tabpanel" class="tab-pane active" id="current-video-data">
																				<div class="row">
																					<div class="col-xs-12">
																						<div v-if="!selectedPartnerObject.filters.https && siteIsHttps" class="text-center text-danger">
																							<img class="img-responsive" v-bind:src="currentVideo.thumb_url">
																							<div class="alert alert-danger text-center margin-top-10 margin-bottom-0" role="alert">
																								<p><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <strong>{{selectedPartnerObject.name}} <?php esc_html_e( 'Embed Code does NOT work with SSL (HTTPS://)', 'lvjm_lang' ); ?></strong></p>
																							</div>
																						</div>
																						<div v-else class="text-center">
																							<img class="img-responsive" style="width:100%" v-bind:src="currentVideo.thumb_url">
																						</div>
																					</div>
																				</div>
																				<div class="row padding-top-15">
																					<div class="col-xs-12 form-horizontal">
																						<div class="form-group">
																							<label for="models" class="col-sm-2 control-label">Models:</label>
																							<div class="col-sm-10">
																								<input type="text" name="actors" class="form-control" v-model="currentVideo.actors" v-bind:disabled="currentVideo.grabbed" placeholder="insert models separated by a comma">
																							</div>
																						</div>
																						<div class="form-group">
																							<label for="tags" class="col-sm-2 control-label">Tags:</label>
																							<div class="col-sm-10">
																								<input type="text" name="tags" class="form-control" v-model="currentVideo.tags" v-bind:disabled="currentVideo.grabbed" placeholder="insert tags separated by a comma">
																							</div>
																						</div>
																					</div>
																				</div>
																				<div class="row">
																					<div class="col-xs-12">
																						<textarea placeholder="<?php esc_html_e( 'Description', 'lvjm_lang' ); ?>..." rows="5" name="" v-model="currentVideo.desc" v-bind:disabled="currentVideo.grabbed" class="form-control"></textarea>
																					</div>
																				</div>
																			</div>
																			<div role="tabpanel" class="tab-pane" id="current-video-thumbs">
																				<div v-if="currentVideo.thumbs_urls != ''" class="row">
																					<transition name="fade" mode="out-in">
																						<div v-if="expandedThumb == ''" key="allThumbs"> 
																							<div v-for="thumb in currentVideo.thumbs_urls" v-bind:key="thumb" class="col-xs-6 col-md-3 item">
																								<img class="img-responsive thumbnail" v-bind:src="thumb" v-on:click="showThumb(thumb)">
																							</div>
																						</div>
																						<div v-if="expandedThumb != ''" class="col-xs-10 col-xs-offset-1 item" key="expandedThumb">
																							<i aria-hidden="true" class="fa fa-times close-expanded-thumb" v-on:click="hideThumb"></i>
																							<img class="img-responsive thumbnail" style="width:100%;" v-bind:src="expandedThumb" v-on:click="hideThumb">
																						</div>
																					</transition>
																					<div class="col-xs-12">
																						<p class="padding-top-10">
																						<?php
																						printf(
																							/* translators: %s: link to wp-script.com themes */
																							esc_html__( 'The thumbnails are used by %1$s to display a preview thumbnails rotation when hovering a video thumbnail.', 'lvjm_lang' ),
																							sprintf(
																								'<a href="%s">%s</a>',
																								esc_url( 'https://www.wp-script.com/themes' ),
																								esc_html__( 'WP-Script themes', 'text-domain' )
																							)
																						);
																						?>
																						</p>
																					</div>
																				</div>
																			</div>
																			<div role="tabpanel" class="tab-pane" id="current-video-trailer">
																				<div v-if="currentVideo.trailer_url != ''" class="row">
																				<div class="col-xs-12">
																					<div class="text-center">
																						<video controls class="embed-responsive-item" v-bind:poster="currentVideo.thumb_url">
																							<source v-bind:src="currentVideo.trailer_url">
																						</video>
																					</div>
																					<div class="col-xs-12">
																						<p class="padding-top-10">
																						<?php
																						printf(
																							/* translators: %s: link to wp-script.com themes */
																							esc_html__( 'The trailers are used by %1$s to display a preview video when hovering a video thumbnail.', 'lvjm_lang' ),
																							sprintf(
																								'<a href="%s">%s</a>',
																								esc_url( 'https://www.wp-script.com/themes' ),
																								esc_html__( 'WP-Script themes', 'text-domain' )
																							)
																						);
																						?>
																						</p>
																					</div>
																				</div>
																				</div>
																			</div>
																		</div>
																	</div>
																</div>
															</div>
															<div class="modal-footer">
																<div class="row">
																	<div class="col-xs-3 col-md-3">
																		<button v-on:click.prevent="prevVideoModal(currentVideo.index)" type="button" class="btn btn-default btn-block"><i class="fa fa-arrow-left"></i></button>
																	</div>
																	<div class="col-xs-6 col-md-6">
																		<button v-if="!currentVideo.grabbed" v-on:click.prevent="toggleVideo(currentVideo.index, 'modal')" type="button" class="btn btn-block" v-bind:class="[currentVideo.checked ? 'btn-success':'btn-default']"><i class="fa-lg" v-bind:class="[currentVideo.checked ? 'fa fa-check-square-o':'fa fa-square-o']" aria-hidden="true"></i> <span v-if="!currentVideo.checked"><?php esc_html_e( 'Check this video', 'lvjm_lang' ); ?></span><span v-else><?php esc_html_e( 'Uncheck this video', 'lvjm_lang' ); ?></span></button>
																		<button v-else type="button" class="btn btn-default btn-block" disabled><?php esc_html_e( 'Video grabbed', 'lvjm_lang' ); ?></button>
																	</div>
																	<div class="col-xs-3 col-md-3">
																		<button v-on:click.prevent="nextVideoModal(currentVideo.index)" type="button" class="btn btn-default btn-block"><i class="fa fa-arrow-right"></i></button>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
												<!--/ video modal-->
											</div>
										</transition>
									</div>
									<!-- /results success block -->
									<!-- search details modal modal -->
										<div v-if="searchedData && searchedData.videos_details" class="modal fade" id="search-details-modal" tabindex="-1" role="dialog" aria-hidden="true">
											<div class="modal-dialog">
												<div class="modal-content">
													<div class="modal-header">
														<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only"><?php esc_html_e( 'Close', 'lvjm_lang' ); ?></span></button>
														<h4 class="modal-title"><?php esc_html_e( 'Search results details', 'lvjm_lang' ); ?></h4>
													</div>
													<div class="modal-body">
														<table class="table table-condensed margin-bottom-0">
															<tr>
																<th>Video ID</th>
																<th>Video response</th>
															</tr>
															<tr v-for="(videoDetail, index) in searchedData.videos_details" v-bind:key="index" v-bind:class="[videoDetail.response === 'Success' ? 'success' : '']">
																<template v-if="videoDetail.id === 'end'">
																	<td colspan="2" class="text-center"><strong>{{videoDetail.response}}</strong></td>
																</template>
																<template v-else>
																	<template v-if="videoDetail.response === 'Success'">
																		<td class="text-success"><small>{{videoDetail.id}}</small></td>
																		<td class="text-success"><small><i class="fa fa-check"></i> {{videoDetail.response}}</small></td>
																	</template>
																	<template v-else>
																		<td><small>{{videoDetail.id}}</small></td>
																		<td><small><i>{{videoDetail.response}}</i></small></td>
																	</template>
																</template>
															</tr>
														</table>
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
													</div>
												</div>
											</div>
										</div>
										<!-- /search details modal modal -->
									<!-- feeds block -->
									<div id="feeds" class="margin-top-80">
										<div class="row">
											<div class="col-xs-12">
												<h3>
													<i class="fa fa-database" aria-hidden="true"></i>  <?php esc_html_e( 'Saved Feeds', 'lvjm_lang' ); ?> ({{data.feeds.length}})
												</h3>
												<span class="form-inline">
													<input v-if="data.feeds.length > 0" type="text" class="form-control" placeholder="filter feeds" v-model="feedsFilter">
													<paginate-links for="feeds" v-bind:hide-single-page="true" v-bind:simple="{prev: 'Prev', next: 'Next'}" v-bind:classes="{'ul': 'pagination'}"></paginate-links>
													<button class="btn btn-link btn-sm" href="#" v-on:click.prevent="toggleSavedSearchesHelp"><?php esc_html_e( 'What are Saved Feeds?', 'lvjm_lang' ); ?></button>
												</span>
											</div>
										</div>
										<div class="row">
											<div class="col-xs-12">
												<div v-show="showSavedSearchesHelp" class="alert alert-info margin-bottom-10">
													<button v-on:click.prevent="toggleSavedSearchesHelp" type="button" class="close" aria-label="Close"><span aria-hidden="true">&times;</span></button>
													<p><?php esc_html_e( 'Whenever you import videos from a new search, a Feed is saved. A Feed is a saved state, keeping your current search linked to the selected WordPress category. Your feeds are listed just bellow, providing informations and actions for each one.', 'lvjm_lang' ); ?></p>
												</div>
											</div>
										</div>
										<paginate name="feeds" v-bind:list="filteredFeeds" v-bind:per="12" class="row" id="feeds-list" tag="div">
											<feed v-if="data.feeds.length > 0" v-for="(feed, index) in paginated('feeds')" v-bind:key="feed.id" v-bind:feed="feed" v-bind:wp-cats="data.WPCats" v-bind:partner-cat-name="getPartnerCatName(feed.partner_id, feed.partner_cat)" v-bind:delete-feed-id="deleteFeedId" v-bind:partners="data.partners" v-bind:smt-is-loading="smtIsLoading" v-bind:auto-import-enabled='data.autoImportEnabled' @confirm-feed-deletion="confirmFeedDeletion" @search-videos="searchVideos"></feed>
										</paginate>
										<p v-if="data.feeds.length == 0"><?php esc_html_e( 'No feed has been saved yet', 'lvjm_lang' ); ?></p>

										<!-- delete video modal -->
										<div v-if="deleteVideo.video" class="modal fade" id="delete-video-modal" tabindex="-1" role="dialog" aria-hidden="true">
											<div class="modal-dialog">
												<div class="modal-content">
													<div class="modal-header">
														<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only"><?php esc_html_e( 'Close', 'lvjm_lang' ); ?></span></button>
														<h4 class="modal-title"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <?php esc_html_e( 'Deletion confirmation', 'lvjm_lang' ); ?></h4>
													</div>
													<div class="modal-body">
																{{deleteVideo.video.thumb_url}}
														<p><?php esc_html_e( 'Do you really want to remove this video? It will never be available again.', 'lvjm_lang' ); ?></p>
														<p><img class="img-responsive thumbnail" style="width:100%" v-bind:src="deleteVideo.video.thumb_url" v-bind:src="deleteVideo.video.title"></p>
														<p class="checkbox">
															<label><input type="checkbox" v-model="removeDeleteVideoConfirmation"> <?php esc_html_e( 'Do not show me this confirmation message anymore for this session', 'lvjm_lang' ); ?></label>
														</p>
													</div>
													<div class="modal-footer">
														<button class="btn btn-danger" v-bind:disabled="loading.removingVideo" v-on:click.prevent="removeVideo"><i class="fa fa-trash-o" v-bind:class="[loading.removingVideo ? 'fa-spinner fa-pulse':'fa-trash-o']" aria-hidden="true"></i> <span v-if="!loading.removingVideo"><?php esc_html_e( 'Delete this video', 'lvjm_lang' ); ?></span><span v-else><?php esc_html_e( 'Deleting video', 'lvjm_lang' ); ?></span></button> <button class="btn btn-default" v-bind:disabled="loading.removingVideo" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'lvjm_lang' ); ?></button>
													</div>
												</div>
											</div>
										</div>
										<!-- /delete feed modal -->
										<!-- delete feed modal -->
										<div class="modal fade" id="delete-feed-modal" tabindex="-1" role="dialog" aria-hidden="true">
											<div class="modal-dialog">
												<div class="modal-content">
													<div class="modal-header">
														<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only"><?php esc_html_e( 'Close', 'lvjm_lang' ); ?></span></button>
														<h4 class="modal-title"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <?php esc_html_e( 'Delete feed confirmation', 'lvjm_lang' ); ?></h4>
													</div>
													<div class="modal-body">
														<p><?php esc_html_e( 'Do you really want to delete this feed? All videos from this feed will be removed.', 'lvjm_lang' ); ?></p>
														<p class="delete-buttons">
														<button class="btn btn-danger action-delete" v-bind:disabled="loading.deleteFeed" v-on:click.prevent="deleteFeed"><i class="fa fa-trash-o" v-bind:class="[loading.deleteFeed ? 'fa-spinner fa-pulse':'fa-trash-o']" aria-hidden="true"></i> <span v-if="!loading.deleteFeed"><?php esc_html_e( 'Delete this feed', 'lvjm_lang' ); ?></span><span v-else><?php esc_html_e( 'Deleting feed', 'lvjm_lang' ); ?></span></button>&nbsp;&nbsp;&nbsp;&nbsp;<button class="btn btn-default" v-bind:disabled="loading.deleteFeed" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'lvjm_lang' ); ?></button></p>
														<div class="clearfix"></div>
													</div>
												</div>
											</div>
										</div>
										<!-- /delete feed modal -->
									</div>
								</div>
							</transition>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}
