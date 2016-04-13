<?php
if (!isset($_SESSION['arrival'])) {
    $_SESSION['arrival'] = '';
}
if (!isset($_SESSION['depart'])) {
    $_SESSION['depart'] = '';
}
?>
<div class="" id="vrpcontainer">
    <div class="row">
        <div class="col-md-8">
            <div class="row">
                <?php echo esc_html($data->Name); ?>
            </div>
            <div class="row">
                <?php echo esc_html($data->Bedrooms); ?> Bedroom(s) | <?php echo esc_html($data->Bathrooms); ?>
                Bathroom(s) | Sleeps <?php echo esc_html($data->Sleeps); ?>
            </div>
        </div>
        <div class="col-md-2">
            <button class="vrp-favorite-button" data-unit="<?php echo $data->id ?>"></button>
        </div>
    </div>

    <div class="row">
        <div id="tabs">
            <ul>
                <li><a href="#overview">Overview</a></li>
                <li><a href="#amenities">Amenities</a></li>
                <?php if (isset($data->reviews[0])) { ?>
                    <li><a href="#reviews">Reviews</a></li>
                <?php } ?>
                <li><a href="#calendar">Book</a></li>
                <li><a href="#rates">Rates</a></li>
                <?php if (isset($data->lat) && isset($data->long)) { ?>
                    <li><a href="#gmap" id="gmaplink">Map</a></li>
                <?php } ?>
            </ul>

            <!-- OVERVIEW TAB -->
            <div id="overview">
                <div class="row">
                    <div class="col-md-12">
                        <!-- Photo Gallery -->
                        <div id="photo">
                            <?php
                            $count = 0;
                            foreach ($data->photos as $k => $v) {
                                $style = "";
                                if ($count > 0) {
                                    $style = "display:none;";
                                }
                                ?>
                                <img id="full<?php echo esc_attr($v->id); ?>"
                                     alt="<?php echo esc_attr($v->caption); ?>"
                                     src="<?php echo $v->url; ?>"
                                     style="width:100%; <?php echo esc_attr($style); ?>"/>
                                <?php
                                $count++;
                            }
                            ?>
                        </div>

                        <div id="gallery">
                            <?php foreach ($data->photos as $k => $v) : ?>
                                <?php $v->thumb_url = $v->url; ?>
                                <img class="thumb"
                                     id="<?php echo esc_attr($v->id); ?>"
                                     alt="<?php echo esc_attr($v->caption); ?>"
                                     src="<?php echo $v->thumb_url; ?>"
                                     style="height:60px; float:left; margin: 3px;"/>
                            <?php endforeach; ?>
                        </div>
                        <br style="clear:both;" class="clearfix">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div id="description">
                            <p><?php echo wp_kses_post(nl2br($data->Description)); ?></p>
                        </div>
                    </div>
                </div>
                <div class="clearfix"></div>
            </div>

            <div id="amenities">
                <table class="amenTable" cellspacing="0">
                    <tr>
                        <td colspan="2" class="heading"><h4>Amenities</h4></td>
                    </tr>
                    <?php foreach ($data->attributes as $amen) { ?>

                        <tr>
                            <td class="first">
                                <b><?php echo esc_html($amen->name); ?></b>:
                            </td>
                            <td> <?php echo esc_html($amen->value); ?></td>
                        </tr>

                    <?php } ?>
                </table>
            </div>

            <?php if (isset($data->reviews[0])) { ?>
                <div id="reviews">
                    <section id="reviews">
                        <h2 >Guest Reviews of <span class="fn"><?= strtolower($data->Name); ?></span></h2>
                        <span class="address serif"><?= $data->Address2; ?> <?= $data->City; ?>,&nbsp;<?= $data->State; ?></span>

                        <?php
                        $total = 0;
                        $rat   = 0;
                        foreach ($data->reviews as $v) {
                            $rat += $v->rating;
                            $total ++;
                        }
                        $av = round($rat / $total, 2);
                        ?>

                        <div class="hreview-aggregate" style="font-size:11px;">
                            <hr>
                            <div class="item vcard">
                                <a href="http://www.flipkey.com/" target="_blank" style="font-size: 1.2em;">Vacation&nbsp;Rental Reviews&nbsp;by <img class="flipkey" style="height: 1.7em; vertical-align: text-top;" src="https://www.flipkey.com/img/marketing/logos/FlipKey-Logo.png"></a>

                                <div style="float:right;text-align:center;" class="serif one-third">
                                    <span class="rating">
                                        <span class="average"><?= $av; ?></span>&nbsp;out&nbsp;of&nbsp;<span class="best">5</span></span>&nbsp;stars
                                    based&nbsp;on
                                    <span class="count"><?= $total; ?></span>&nbsp;user&nbsp;reviews
                                </div>
                            </div>
                        </div>

                        <?php foreach ($data->reviews as $review): ?>
                            <div class="review-post">
                                <div class="hreview">
                                    <h3 class="title" style="margin-bottom:12px;"><?= $review->title; ?></h3>

                                    <?php if(!empty($review->name)) : ?>
                                        <b class="reviewer vcard">Review by <span class="fn"><?= $review->name; ?></span></b>
                                    <?php endif; ?>

                                    <div class="description item vcard">
                                        <span class="serif"><?= strip_tags($review->review); ?></span><br>
                                        <b class="rating"><?= $review->rating; ?> out of 5 stars</b>
                                    </div>
                                </div>
                                <?php if (!empty($review->response)) { ?>
                                    <div class="reviewresponse" style="margin-top:1em;padding-top:1em;border-top:1px solid #dadada;">
                                        <h5 class="title" > Manager Response:</h5>
                                        <?= $review->response; ?>
                                    </div>
                                <?php } ?>
                            </div>

                        <?php endforeach; ?>
                </div>
            <?php } ?>

            <!-- CALENDAR TAB -->
            <div id="calendar">
                <div class="row">
                    <div class="col-md-6">
                        <div id="checkavailbox">
                            <h1 class="bookheading">Book Your Stay!</h1><br>

                            <div id="datespicked">
                                Select your arrival and departure dates below to reserve this unit.<br><br>

                                <form action="<?php echo esc_url(site_url('/vrp/book/step3/', 'https')); ?>"
                                      method="get" id="bookingform">

                                    <table align="center" width="96%">
                                        <tr>
                                            <td width="40%">Arrival:</td>
                                            <td>
                                                <input type="text" id="arrival2" name="obj[Arrival]"
                                                       class="input unitsearch"
                                                       value="<?php echo esc_attr($_SESSION['arrival']); ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Departure:</td>
                                            <td>
                                                <input type="text" id="depart2" name="obj[Departure]"
                                                       class="input unitsearch"
                                                       value="<?php echo esc_attr($_SESSION['depart']); ?>">
                                            </td>
                                        </tr>

                                        <?php if ($data->manager->Name == "Escapia" && !empty($data->additonal->PetsPolicy)) :?>
                                            <!-- Escapia PMS - Booking w/Pets -->
                                            <?php if($data->additonal->PetsPolicy == 2) : ?>
                                                <?php $petsType = "Dog"; ?>
                                            <?php elseif ($data->additonal->PetsPolicy == 1) : ?>
                                                <?php $petsType = "Cat"; ?>
                                            <?php endif; ?>

                                            <tr>
                                                <td>Pets:</td>
                                                <td>
                                                    <select name="obj[Pets]">
                                                        <option value="">None</option>
                                                        <option value="<?=$data->additonal->PetsPolicy?>"><?=$petsType?>(s)</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                        <tr id="errormsg">
                                            <td colspan="2">
                                                <div></div>
                                                &nbsp;
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">
                                                <div id="ratebreakdown"></div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">
                                                <input type="hidden" name="obj[PropID]"
                                                       value="<?php echo esc_attr($data->id); ?>">
                                                <input type="button" value="Check Availability"
                                                       class="bookingbutton rounded" id="checkbutton">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="right" colspan="2">
                                                <input type="submit" value="Book Now!" id="booklink" class=""
                                                       style="display:none;"/>
                                            </td>
                                        </tr>
                                    </table>

                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div id="availability" style="">
                            <?php echo wp_kses_post(vrpCalendar($data->avail)); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="rates">
                <div class="row">
                    <h3>Seasonal Rates</h3>

                    <div id="rates">
                        <?php
                        $r = [];
                        foreach ($data->rates as $v) {
                            $start = date("m/d/Y", strtotime($v->start_date));
                            $end = date("m/d/Y", strtotime($v->end_date));
                            $r[$start . " - " . $end] = new \stdClass();

                            if ($v->chargebasis == 'Monthly') {
                                $r[$start . " - " . $end]->monthly = "$" . $v->amount;
                            }
                            if ($v->chargebasis == 'Daily') {
                                $r[$start . " - " . $end]->daily = "$" . $v->amount;
                            }
                            if ($v->chargebasis == 'Weekly') {
                                $r[$start . " - " . $end]->weekly = "$" . $v->amount;
                            }
                        }
                        ?>

                        <table cellpadding="3">
                            <tr>
                                <th>Date Range</th>
                                <th>Rate</th>
                            </tr>
                            <?php
                            foreach ($r as $k => $v) {
                                if (isset($v->daily)) {
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($k); ?>
                                        </td>
                                        <td><?php echo esc_html($v->daily); ?>/nt</td>
                                    </tr>
                                <?php }
                            } ?>
                        </table>
                        * Seasonal rates are only estimates and do not reflect taxes or additional fees.
                    </div>
                </div>
            </div>

            <div id="gmap">
                <div id="map" style="width:100%;height:500px;"></div>
            </div>


        </div>
    </div>
</div>


<!-- GOOGLE MAPS SCRIPT -->

<script type="text/javascript">
    var geocoder, map, value = "<?php echo esc_attr( $data->id ); ?>", image = '<?php bloginfo( 'template_directory' ); ?>/images/mapicon.png';

    function initialize() {
        geocoder = new google.maps.Geocoder();
        var myOptions = {
            zoom: 13,
            <?php if ( strlen( $data->lat ) > 0 && strlen( $data->long ) > 0 ) { ?>
            center: new google.maps.LatLng(<?php echo esc_js( $data->lat ); ?>, <?php echo esc_js( $data->long ); ?>),
            <?php } ?>
            scrollwheel: false,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        };
        map = new google.maps.Map(document.getElementById("map"), myOptions);
        <?php if ( strlen( $data->lat ) == 0 || strlen( $data->long ) == 0 ) { ?>
        codeAddress();
        <?php } ?>
    }

    function codeAddress() {
        var query = "<?php echo esc_js( $data->Address1 ) . " " . esc_js( $data->Address2 ) . " " . esc_js( $data->City ) . " " . esc_js( $data->State ) . " " . esc_js( $data->PostalCode ); ?>";
        var address = query;
        geocoder.geocode({'address': address}, function (results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                    map.setCenter(results[0].geometry.location);
                    var marker = new google.maps.Marker({
                        map: map,
                        position: results[0].geometry.location,
                        //icon: image
                    });
                } else {
                    //alert("Geocode was not successful for the following reason: " + status);
                }
            }
        )
        ;

    }

    jQuery(document).ready(function () {
        jQuery("#gmaplink").on('click', function () {
            initialize();
        });
    });

    if (<?php echo (isset($data->pageViews)) ? "true" : "false"; ?>) {
        jQuery.get(url_paths.site_url, {vrpjax: true, act: "saveUnitPageView", par:<?php echo $data->id;?>, cache:Math.random()});
    }
</script>

