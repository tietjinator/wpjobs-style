<?php
/**
 * Sample listings for previewing the plugin: a Jobs page, a Post a Job page, a
 * dashboard page, and about thirty realistic church and ministry job listings.
 *
 * All churches, people and websites are made up. Websites and emails use
 * example.org, which is reserved for examples.
 *
 * Run inside WordPress: wp eval-file sample-data/seed.php
 * Or: WP_LOAD=/path/to/wp-load.php php sample-data/seed.php
 *
 * It is inlined into blueprint.json by sample-data/build-blueprint.php, which is
 * how the Playground preview gets its content. Edit the data here, then run
 *   php sample-data/build-blueprint.php
 * to refresh the blueprint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	require getenv( 'WP_LOAD' ) ? getenv( 'WP_LOAD' ) : '/wordpress/wp-load.php';
}

// ---------------------------------------------------------------------------
// Settings the listings rely on: categories, salary and the remote checkbox.
// ---------------------------------------------------------------------------
update_option( 'job_manager_enable_types', 1 );
update_option( 'job_manager_enable_categories', 1 );
update_option( 'job_manager_enable_remote_position', 1 );
update_option( 'job_manager_enable_salary', 1 );
update_option( 'job_manager_enable_salary_currency', 1 );
update_option( 'job_manager_enable_salary_unit', 1 );
update_option( 'job_manager_per_page', 15 );

// The plugin registers its category and type taxonomies at startup, and only if
// these settings are already on. In the Playground blueprint they are saved in an
// earlier step, so that has happened. Run on its own, this is the first request
// to switch them on, so register stand-ins for this run. The saved terms are
// ordinary database rows that the plugin picks up on the next page load.
if ( ! taxonomy_exists( 'job_listing_category' ) ) {
	register_taxonomy( 'job_listing_category', 'job_listing', [ 'hierarchical' => true, 'public' => false ] );
}
if ( ! taxonomy_exists( 'job_listing_type' ) ) {
	register_taxonomy( 'job_listing_type', 'job_listing', [ 'hierarchical' => false, 'public' => false ] );
}

// Term IDs by name, so listings are assigned by ID. (Names with an ampersand are
// stored encoded, which makes looking them up by name unreliable.)
$term_ids = [];
$ensure   = function ( $name, $taxonomy ) use ( &$term_ids ) {
	$found = term_exists( $name, $taxonomy );
	if ( ! $found ) {
		$found = wp_insert_term( $name, $taxonomy );
	}
	$term_ids[ $taxonomy ][ $name ] = is_array( $found ) ? (int) $found['term_id'] : 0;
};
foreach ( [ 'Full Time', 'Part Time', 'Internship', 'Freelance', 'Temporary' ] as $t ) {
	$ensure( $t, 'job_listing_type' );
}
foreach ( [ 'Pastoral & Leadership', 'Worship & Creative Arts', 'Children & Youth', 'Administration & Operations', 'Missions & Outreach', 'Communications & Media' ] as $c ) {
	$ensure( $c, 'job_listing_category' );
}

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------
$make_page = function ( $title, $slug, $content, $option ) {
	$existing = get_page_by_path( $slug );
	$id       = $existing ? $existing->ID : wp_insert_post( [ 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => $content ] );
	update_option( $option, $id );
};
$make_page( 'Jobs', 'jobs', '[jobs]', 'job_manager_jobs_page_id' );
$make_page( 'Post a Job', 'post-a-job', '[submit_job_form]', 'job_manager_submit_job_form_page_id' );
$make_page( 'Job Dashboard', 'job-dashboard', '[job_dashboard]', 'job_manager_job_dashboard_page_id' );

// ---------------------------------------------------------------------------
// A small valid PDF for the listings that have a "full job description".
// ---------------------------------------------------------------------------
$make_pdf = function ( $title, $church, $lines ) {
	$esc     = function ( $s ) {
		return str_replace( [ '\\', '(', ')' ], [ '\\\\', '\\(', '\\)' ], $s );
	};
	$content = "BT /F1 22 Tf 60 730 Td ({$esc( $title )}) Tj ET\nBT /F1 13 Tf 60 706 Td ({$esc( $church )}) Tj ET\n";
	$y       = 664;
	foreach ( $lines as $line ) {
		$content .= "BT /F1 11 Tf 60 $y Td ({$esc( $line )}) Tj ET\n";
		$y       -= 20;
	}
	$objects   = [];
	$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
	$objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
	$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>';
	$objects[] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . 'endstream';
	$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
	$pdf       = "%PDF-1.4\n";
	$offsets   = [];
	foreach ( $objects as $i => $body ) {
		$offsets[] = strlen( $pdf );
		$pdf      .= ( $i + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
	}
	$xref = strlen( $pdf );
	$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
	foreach ( $offsets as $o ) {
		$pdf .= sprintf( "%010d 00000 n \n", $o );
	}
	$pdf .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";

	return $pdf;
};

// ---------------------------------------------------------------------------
// The listings
//   title, church, website, [city, state, zip], type, category, days ago, flags,
//   salary [text, unit, currency] or null, text parts
// flags: featured, remote, filled, pdf
// ---------------------------------------------------------------------------
$listings = [
	[
		'title' => 'Lead Pastor', 'church' => 'Grace Community Church', 'site' => 'https://example.org/grace',
		'loc' => [ 'Fort Wayne', 'IN', '46802' ], 'type' => 'Full Time', 'cat' => 'Pastoral & Leadership', 'days' => 2,
		'flags' => [ 'featured', 'pdf' ], 'pay' => [ '85,000 - 110,000', 'year' ],
		'intro' => 'Grace Community Church, a congregation of about 450 across two Sunday services, is searching for a Lead Pastor to shepherd our church into its next season of growth. We are a multigenerational community in the heart of Fort Wayne with a strong tradition of prayer, hospitality and service to our neighbours.',
		'do'    => [ 'Preach and teach on Sundays and lead the vision and spiritual direction of the church', 'Shepherd, equip and care for a staff team of six and a volunteer base of over 150', 'Provide pastoral care, including hospital visits, weddings, funerals and counselling', 'Work with the elder board on strategy, budget and long-term planning', 'Represent the church in the community and to our partner ministries' ],
		'need'  => [ 'Ordained, or eligible for ordination, with at least five years of pastoral experience', 'A track record of preaching and teaching the Scriptures with clarity and care', 'Demonstrated leadership of staff and volunteers', 'A seminary degree is preferred but not required' ],
		'about' => 'We worship in a historic sanctuary with a modern gathering space, and our ministries reach families, students, seniors and a growing Spanish-language congregation.',
	],
	[
		'title' => 'Executive Pastor', 'church' => 'Crossroads Church', 'site' => 'https://example.org/crossroads',
		'loc' => [ 'Dallas', 'TX', '75201' ], 'type' => 'Full Time', 'cat' => 'Pastoral & Leadership', 'days' => 1,
		'flags' => [ 'featured', 'pdf' ], 'pay' => [ '95,000 - 120,000', 'year' ],
		'intro' => 'Crossroads Church is looking for an Executive Pastor to lead the day-to-day operations of a multi-site church of 1,800 and to free our pastors to focus on ministry. You will oversee staff, finance, facilities and systems, and help us steward rapid growth well.',
		'do'    => [ 'Lead and develop a staff of 28 across three campuses', 'Oversee a $4.2M operating budget, financial reporting and annual planning', 'Manage facilities, technology and operations, including the new building project', 'Build clear systems, policies and communication across campuses', 'Serve as the key partner to the Lead Pastor and the elder board' ],
		'need'  => [ 'Eight or more years of leadership experience, ideally in a church or nonprofit', 'Strong financial and organizational skills', 'A collaborative, calm and decisive leadership style', 'A deep commitment to the mission and theology of the church' ],
		'about' => 'Crossroads has grown from 300 to 1,800 in a decade. We are a church that values clarity, generosity and developing leaders.',
	],
	[
		'title' => 'Worship Pastor', 'church' => 'New Hope Church', 'site' => 'https://example.org/newhope',
		'loc' => [ 'Colorado Springs', 'CO', '80903' ], 'type' => 'Full Time', 'cat' => 'Worship & Creative Arts', 'days' => 5,
		'flags' => [ 'pdf' ], 'pay' => [ '62,000 - 76,000', 'year' ],
		'intro' => 'New Hope Church is looking for a Worship Pastor who loves to lead people into the presence of God and to develop the musicians, vocalists and technical volunteers who serve alongside them.',
		'do'    => [ 'Plan and lead worship for two Sunday services and special services throughout the year', 'Recruit, coach and schedule a team of about 40 musicians, singers and tech volunteers', 'Select songs, build set lists and coordinate with the preaching team', 'Lead rehearsals and develop emerging worship leaders', 'Oversee audio, lighting and livestream production' ],
		'need'  => [ 'Proficiency on guitar or keys and strong vocal ability', 'Experience leading worship in a church setting', 'The ability to build teams and give clear, kind feedback', 'Working knowledge of sound and presentation software' ],
		'about' => 'Our services blend contemporary worship with the great hymns of the faith, and we are passionate about congregational singing.',
	],
	[
		'title' => 'Youth Pastor', 'church' => 'Riverside Community Church', 'site' => 'https://example.org/riverside',
		'loc' => [ 'Marion', 'IN', '46952' ], 'type' => 'Full Time', 'cat' => 'Children & Youth', 'days' => 3,
		'flags' => [ 'featured' ], 'pay' => [ '48,000 - 58,000', 'year' ],
		'intro' => 'Riverside Community Church is seeking a Youth Pastor to lead our middle and high school ministry of about 70 students. You will help young people meet Jesus, grow in their faith and find a home in the church.',
		'do'    => [ 'Lead weekly youth gatherings and small groups for grades 6 to 12', 'Build and train a team of volunteer leaders and mentors', 'Plan retreats, camps, service projects and a summer mission trip', 'Partner with parents and communicate regularly with families', 'Follow our child protection policies and keep clear records' ],
		'need'  => [ 'Experience in youth ministry, paid or volunteer', 'A genuine love for students and a strong sense of humour', 'Willingness to complete a background check and safe church training' ],
		'about' => 'Our students are curious, kind and ready to serve. The previous youth pastor is moving to plant a church and will help with the transition.',
	],
	[
		'title' => 'Campus Pastor', 'church' => 'Summit Church', 'site' => 'https://example.org/summit',
		'loc' => [ 'Denver', 'CO', '80202' ], 'type' => 'Full Time', 'cat' => 'Pastoral & Leadership', 'days' => 3,
		'flags' => [], 'pay' => [ '70,000 - 88,000', 'year' ],
		'intro' => 'Summit Church is launching a fourth campus in south Denver and is looking for a Campus Pastor to lead it from the start. This is an opportunity to build a new community alongside an experienced, supportive multi-site team.',
		'do'    => [ 'Lead and shepherd the new campus, including the weekend experience and staff', 'Build a launch team and a culture of prayer, hospitality and discipleship', 'Provide pastoral care and develop lay leaders and small groups', 'Collaborate with central ministries on teaching, kids and student ministry' ],
		'need'  => [ 'At least three years of pastoral or ministry leadership experience', 'Experience in church planting or a campus launch is a plus', 'Entrepreneurial, relational and resilient' ],
		'about' => 'Summit has been a leading voice for generosity and local mission in Denver for over twenty years.',
	],
	[
		'title' => "Children's Ministry Director", 'church' => 'Cornerstone Fellowship', 'site' => 'https://example.org/cornerstone',
		'loc' => [ 'Greenville', 'SC', '29601' ], 'type' => 'Full Time', 'cat' => 'Children & Youth', 'days' => 6,
		'flags' => [], 'pay' => [ '44,000 - 52,000', 'year' ],
		'intro' => 'Cornerstone Fellowship is looking for a Children\'s Ministry Director with a passion for helping kids know and love Jesus. You will lead our nursery through fifth grade ministry and equip families to disciple their children at home.',
		'do'    => [ 'Oversee Sunday programming for about 120 children each week', 'Recruit, train and care for more than 60 volunteers', 'Choose and adapt curriculum and plan seasonal events such as VBS', 'Maintain a safe, welcoming environment and follow all safety policies', 'Resource parents with simple, practical tools for faith at home' ],
		'need'  => [ 'Experience in children\'s ministry or elementary education', 'Strong organization and a warm, energetic presence', 'A bachelor\'s degree or equivalent experience' ],
		'about' => 'Our families are at the centre of what we do, and we are proud of the kids who grow up here.',
	],
	[
		'title' => 'Communications Director', 'church' => 'Mosaic Church', 'site' => 'https://example.org/mosaic',
		'loc' => [ 'Portland', 'OR', '97201' ], 'type' => 'Full Time', 'cat' => 'Communications & Media', 'days' => 7,
		'flags' => [ 'remote' ], 'pay' => [ '58,000 - 72,000', 'year' ],
		'intro' => 'Mosaic Church is looking for a Communications Director to tell the story of what God is doing in our city. You will shape our voice, manage our channels and lead a small creative team. This role is open to remote candidates, with occasional visits to Portland.',
		'do'    => [ 'Develop and carry out a communications plan across web, email, social and print', 'Lead a team of one designer, one videographer and several volunteers', 'Write and edit stories, announcements and newsletters', 'Maintain brand standards and a consistent, warm voice', 'Report on what is working and adjust the plan' ],
		'need'  => [ 'Three or more years in communications, marketing or journalism', 'Excellent writing and editing skills', 'Comfort with design tools, email platforms and basic analytics' ],
		'about' => 'We are a diverse church of about 600 and a good place to do thoughtful, creative work.',
	],
	[
		'title' => 'Associate Pastor of Discipleship', 'church' => 'Trinity Fellowship', 'site' => 'https://example.org/trinity',
		'loc' => [ 'Nashville', 'TN', '37201' ], 'type' => 'Full Time', 'cat' => 'Pastoral & Leadership', 'days' => 14,
		'flags' => [], 'pay' => [ '60,000 - 72,000', 'year' ],
		'intro' => 'Trinity Fellowship is looking for an Associate Pastor of Discipleship to design and lead how people at our church grow in faith, from new believers to seasoned leaders.',
		'do'    => [ 'Design clear pathways for spiritual growth, including classes, groups and mentoring', 'Lead and train small group leaders', 'Teach in adult classes and occasionally preach', 'Support new members as they find their place in the church' ],
		'need'  => [ 'Ministry experience and a seminary degree or equivalent training', 'A teacher\'s heart and the ability to organize people and process' ],
		'about' => 'Trinity is a thoughtful, warm church of about 350 near downtown Nashville.',
	],
	[
		'title' => 'Church Administrator', 'church' => 'Good Shepherd Church', 'site' => 'https://example.org/goodshepherd',
		'loc' => [ 'Albany', 'NY', '12207' ], 'type' => 'Full Time', 'cat' => 'Administration & Operations', 'days' => 18,
		'flags' => [], 'pay' => [ '50,000 - 62,000', 'year' ],
		'intro' => 'Good Shepherd Church is looking for an organized, gracious Church Administrator to keep our ministry running smoothly and support our pastor and staff.',
		'do'    => [ 'Manage the church office, calendar, building use and vendor relationships', 'Support payroll, accounts payable and basic bookkeeping', 'Maintain membership records and communication tools', 'Coordinate volunteers and welcome visitors to the office' ],
		'need'  => [ 'Three years of office or administrative experience', 'Proficiency with email, spreadsheets and church management software', 'Discretion, kindness and attention to detail' ],
		'about' => 'We are a close-knit church of about 200 with a long history in the capital district.',
	],
	[
		'title' => 'Facilities & Grounds Manager', 'church' => 'Lakeside Church', 'site' => 'https://example.org/lakeside',
		'loc' => [ 'Orlando', 'FL', '32801' ], 'type' => 'Full Time', 'cat' => 'Administration & Operations', 'days' => 9,
		'flags' => [], 'pay' => [ '46,000 - 58,000', 'year' ],
		'intro' => 'Lakeside Church is looking for a Facilities & Grounds Manager to care for our 40-acre campus and the people who use it each week.',
		'do'    => [ 'Maintain buildings, systems and grounds, including routine repairs and preventive care', 'Set up and tear down for services and events', 'Manage contractors, vendors and a small team of volunteers', 'Keep the campus safe, clean and welcoming' ],
		'need'  => [ 'Experience in facilities, maintenance or a related trade', 'Ability to lift 50 lbs and work some evenings and weekends', 'A valid driver\'s license' ],
		'about' => 'Our campus hosts services, a preschool and community events almost every day of the week.',
	],
	[
		'title' => 'Audio / Video Technician', 'church' => 'Lighthouse Church', 'site' => 'https://example.org/lighthouse',
		'loc' => [ 'Phoenix', 'AZ', '85004' ], 'type' => 'Part Time', 'cat' => 'Worship & Creative Arts', 'days' => 2,
		'flags' => [], 'pay' => [ '20 - 26', 'hour' ],
		'intro' => 'Lighthouse Church needs a part-time Audio / Video Technician to run sound, lighting and video for our weekend services and special events, about 20 hours a week.',
		'do'    => [ 'Mix audio for services and rehearsals', 'Operate lighting, slides and the livestream', 'Maintain equipment and train volunteer technicians' ],
		'need'  => [ 'Experience with digital mixing consoles and streaming software', 'Availability on Sundays and Wednesday evenings' ],
		'about' => '',
	],
	[
		'title' => 'Administrative Assistant', 'church' => 'Hillcrest Church', 'site' => 'https://example.org/hillcrest',
		'loc' => [ 'Wichita', 'KS', '67202' ], 'type' => 'Part Time', 'cat' => 'Administration & Operations', 'days' => 4,
		'flags' => [], 'pay' => [ '18 - 22', 'hour' ],
		'intro' => 'Hillcrest Church is hiring a part-time Administrative Assistant, about 24 hours per week, to greet visitors, answer the phone and support our pastor.',
		'do'    => [ 'Welcome visitors and answer calls and email', 'Prepare bulletins and weekly announcements', 'Keep records and support the office team' ],
		'need'  => [ 'A friendly, dependable approach and good computer skills' ],
		'about' => '',
	],
	[
		'title' => 'Student Ministries Assistant', 'church' => 'Oak Grove Church', 'site' => 'https://example.org/oakgrove',
		'loc' => [ 'Charlotte', 'NC', '28202' ], 'type' => 'Part Time', 'cat' => 'Children & Youth', 'days' => 6,
		'flags' => [], 'pay' => [ '17 - 20', 'hour' ],
		'intro' => 'Oak Grove Church is looking for a part-time Student Ministries Assistant to help our youth pastor with events, communication and care for students.',
		'do'    => [ 'Help plan and run weekly youth events', 'Send weekly communication to students and parents', 'Mentor a group of students' ],
		'need'  => [ 'A heart for students and the ability to pass a background check' ],
		'about' => '',
	],
	[
		'title' => 'Missions Coordinator', 'church' => 'Pathways Church', 'site' => 'https://example.org/pathways',
		'loc' => [ 'Seattle', 'WA', '98101' ], 'type' => 'Part Time', 'cat' => 'Missions & Outreach', 'days' => 8,
		'flags' => [], 'pay' => [ '24,000 - 30,000', 'year' ],
		'intro' => 'Pathways Church is looking for a part-time Missions Coordinator to connect our congregation with local outreach and the missionaries we support around the world.',
		'do'    => [ 'Coordinate partnerships with local nonprofits and global missions partners', 'Organize short-term mission trips and service days', 'Communicate stories and needs to the congregation' ],
		'need'  => [ 'Cross-cultural experience, strong communication and organization' ],
		'about' => 'About 20 hours a week, with some flexibility.',
	],
	[
		'title' => 'Bilingual Outreach Coordinator', 'church' => 'Esperanza Church', 'site' => 'https://example.org/esperanza',
		'loc' => [ 'San Antonio', 'Texas', '78205' ], 'type' => 'Full Time', 'cat' => 'Missions & Outreach', 'days' => 7,
		'flags' => [], 'pay' => [ '42,000 - 50,000', 'year' ],
		'intro' => 'Esperanza Church is looking for a Bilingual Outreach Coordinator (English and Spanish) to build relationships with our neighbourhood and connect families with the church and community resources.',
		'do'    => [ 'Lead neighbourhood outreach, food distribution and family events', 'Build partnerships with schools, clinics and local agencies', 'Recruit and train bilingual volunteers' ],
		'need'  => [ 'Fluent in English and Spanish', 'Experience in community outreach or ministry', 'Reliable transportation' ],
		'about' => 'Our services are held in both languages and our neighbourhood is our mission field.',
	],
	[
		'title' => 'Director of Care Ministries', 'church' => 'Gracepoint Church', 'site' => 'https://example.org/gracepoint',
		'loc' => [ 'Minneapolis', 'MN', '55401' ], 'type' => 'Full Time', 'cat' => 'Pastoral & Leadership', 'days' => 5,
		'flags' => [], 'pay' => [ '52,000 - 64,000', 'year' ],
		'intro' => 'Gracepoint Church is looking for a Director of Care Ministries to lead how we walk with people through illness, grief, crisis and life transitions.',
		'do'    => [ 'Lead and train a team of lay care volunteers and visitors', 'Provide pastoral care and coordinate meals, visits and support groups', 'Connect people to counselling and community resources' ],
		'need'  => [ 'Experience in pastoral care, chaplaincy or counselling', 'Compassion, discretion and clear boundaries' ],
		'about' => '',
	],
	[
		'title' => 'Chaplain (Hospital Partnership)', 'church' => 'Mercy Chapel Ministries', 'site' => 'https://example.org/mercychapel',
		'loc' => [ 'Chicago', 'IL', '60601' ], 'type' => 'Part Time', 'cat' => 'Pastoral & Leadership', 'days' => 13,
		'flags' => [], 'pay' => [ '28 - 34', 'hour' ],
		'intro' => 'Mercy Chapel Ministries partners with two Chicago hospitals and is looking for a part-time Chaplain to offer spiritual care to patients, families and staff.',
		'do'    => [ 'Visit patients and families and offer prayer and support', 'Be available for on-call needs one weekend a month', 'Work with the hospital care team' ],
		'need'  => [ 'Ordained or commissioned, with CPE training or equivalent experience' ],
		'about' => '',
	],
	[
		'title' => 'Church Planting Resident', 'church' => 'Harvest City Church', 'site' => 'https://example.org/harvestcity',
		'loc' => [ 'Atlanta', 'GA', '30303' ], 'type' => 'Internship', 'cat' => 'Missions & Outreach', 'days' => 11,
		'flags' => [], 'pay' => [ '1,800', 'month' ],
		'intro' => 'Harvest City Church is offering a twelve-month Church Planting Residency for emerging leaders who sense a call to plant or revitalize a church. You will work alongside our lead planting pastor and learn by doing.',
		'do'    => [ 'Take part in every part of a young church: preaching, care, outreach and administration', 'Meet weekly with a mentor and join a cohort of fellow residents', 'Help lead a new community group' ],
		'need'  => [ 'A sense of calling and a record of faithful service', 'A bachelor\'s degree or equivalent' ],
		'about' => 'A stipend, housing help and ministry coaching are included.',
	],
	[
		'title' => 'Worship Arts Intern', 'church' => 'Harbor Church', 'site' => 'https://example.org/harbor',
		'loc' => [ 'Boston', 'MA', '02108' ], 'type' => 'Internship', 'cat' => 'Worship & Creative Arts', 'days' => 15,
		'flags' => [], 'pay' => [ '1,500', 'month' ],
		'intro' => 'Harbor Church is offering a nine-month Worship Arts Internship for musicians, vocalists and creatives who want to grow in leading and producing worship.',
		'do'    => [ 'Serve on the worship team and learn from our staff', 'Help with production, rehearsals and planning', 'Complete a reading and mentoring program' ],
		'need'  => [ 'Musical ability, a teachable spirit and flexible evenings' ],
		'about' => '',
	],
	[
		'title' => 'Summer Camp Counselor', 'church' => 'Lakeshore Camp & Retreat', 'site' => 'https://example.org/lakeshore',
		'loc' => [ 'Muskegon', 'MI', '49440' ], 'type' => 'Temporary', 'cat' => 'Children & Youth', 'days' => 12,
		'flags' => [], 'pay' => [ '15', 'hour' ],
		'intro' => 'Lakeshore Camp & Retreat is hiring summer counselors for ten weeks of overnight camps on the shore of Lake Michigan. Housing and meals are included.',
		'do'    => [ 'Lead a cabin of campers and help them make friends and grow in faith', 'Lead games, crafts, worship and outdoor activities', 'Keep campers safe and follow all camp policies' ],
		'need'  => [ 'At least 18 years old, energetic and kind', 'First aid training (we can provide it)' ],
		'about' => 'Dates: early June through mid August.',
	],
	[
		'title' => 'Interim Pastor', 'church' => 'Maple Street Church', 'site' => 'https://example.org/maplestreet',
		'loc' => [ 'Burlington', 'VT', '05401' ], 'type' => 'Temporary', 'cat' => 'Pastoral & Leadership', 'days' => 16,
		'flags' => [], 'pay' => [ '4,500', 'month' ],
		'intro' => 'Maple Street Church is seeking an Interim Pastor for about eight months while we search for our next senior pastor. We need a steady, caring leader to preach, provide pastoral care and help the church through a season of transition.',
		'do'    => [ 'Preach each Sunday and lead worship', 'Provide pastoral care and support the staff', 'Work with the search committee as needed' ],
		'need'  => [ 'Experience in pastoral ministry, ideally as an interim' ],
		'about' => '',
	],
	[
		'title' => 'Bookkeeper (Contract)', 'church' => 'Cedar Valley Church', 'site' => 'https://example.org/cedarvalley',
		'loc' => [ 'Cedar Rapids', 'IA', '52401' ], 'type' => 'Freelance', 'cat' => 'Administration & Operations', 'days' => 10,
		'flags' => [ 'remote' ], 'pay' => [ '35 - 45', 'hour' ],
		'intro' => 'Cedar Valley Church is looking for an experienced contract Bookkeeper, about 15 hours a month, to keep our books accurate and our reporting clear. This work can be done remotely.',
		'do'    => [ 'Record income and expenses and reconcile accounts monthly', 'Prepare financial reports for the finance committee', 'Support the annual audit and tax filings' ],
		'need'  => [ 'Bookkeeping experience, ideally with a church or nonprofit', 'Proficiency with QuickBooks Online' ],
		'about' => '',
	],
	[
		'title' => 'Graphic Designer (Freelance)', 'church' => 'Beacon Church', 'site' => 'https://example.org/beacon',
		'loc' => [], 'type' => 'Freelance', 'cat' => 'Communications & Media', 'days' => 20,
		'flags' => [ 'remote' ], 'pay' => [ '40 - 60', 'hour' ],
		'intro' => 'Beacon Church is looking for a freelance Graphic Designer for sermon series branding, social graphics and printed pieces. Projects come up about twice a month and the work is fully remote.',
		'do'    => [ 'Design series branding, social graphics and event materials', 'Prepare print-ready files for local printers', 'Work from a brand guide and a clear creative brief' ],
		'need'  => [ 'A portfolio showing strong typography and layout', 'Experience with Adobe Creative Cloud or Figma', 'Reliable turnaround on short timelines' ],
		'about' => '',
	],
	[
		'title' => 'Preschool Teacher', 'church' => 'Little Lambs Preschool at First Church', 'site' => 'https://example.org/littlelambs',
		'loc' => [ 'Omaha', 'NE', '68102' ], 'type' => 'Full Time', 'cat' => 'Children & Youth', 'days' => 21,
		'flags' => [ 'filled' ], 'pay' => [ '17 - 21', 'hour' ],
		'intro' => 'Little Lambs Preschool is hiring a lead teacher for our four-year-old classroom. This position has been filled.',
		'do'    => [ 'Plan and teach a play-based curriculum', 'Partner with families and our director' ],
		'need'  => [ 'Early childhood education experience' ],
		'about' => '',
	],
	[
		'title' => 'Pastor of Community Life', 'church' => 'Westside Fellowship', 'site' => 'https://example.org/westside',
		'loc' => [ 'Calgary', 'AB', 'T2P 1J9' ], 'type' => 'Full Time', 'cat' => 'Pastoral & Leadership', 'days' => 4,
		'flags' => [], 'pay' => [ '68,000 - 82,000', 'year', 'CAD' ],
		'intro' => 'Westside Fellowship is looking for a Pastor of Community Life to lead our small groups, hospitality and care for newcomers, and to help people in our growing neighbourhood find belonging.',
		'do'    => [ 'Lead and multiply small groups', 'Welcome and connect newcomers', 'Provide pastoral care alongside the lead pastor' ],
		'need'  => [ 'Pastoral experience and a relational, organized approach', 'Eligible to work in Canada' ],
		'about' => 'Westside is a church of about 300 in northwest Calgary.',
	],
	[
		'title' => 'Worship Leader', 'church' => 'Bayview Church', 'site' => 'https://example.org/bayview',
		'loc' => [ 'Toronto', 'Ontario', 'M5H 2N2' ], 'type' => 'Part Time', 'cat' => 'Worship & Creative Arts', 'days' => 9,
		'flags' => [], 'pay' => [ '32 - 40', 'hour', 'CAD' ],
		'intro' => 'Bayview Church in downtown Toronto is looking for a part-time Worship Leader, about 20 hours a week, to lead our Sunday gathering and grow our worship team.',
		'do'    => [ 'Lead worship and plan the Sunday gathering', 'Coach vocalists and musicians' ],
		'need'  => [ 'Guitar or piano, a strong voice and a collaborative spirit' ],
		'about' => '',
	],
];

// Shared closing text by type.
$offer = [
	'Full Time'  => [ 'Health, dental and vision insurance', 'Retirement plan with a church match', 'Three weeks of paid vacation plus holidays', 'Continuing education and ministry development funds' ],
	'Part Time'  => [ 'Flexible scheduling around services and events', 'Paid time off that grows with service', 'A supportive, mission-minded team' ],
	'Internship' => [ 'A monthly stipend', 'Weekly mentoring and a cohort of fellow interns', 'A letter of recommendation on completion' ],
	'Temporary'  => [ 'A defined season of service with a clear start and end date', 'Support and training from our staff team' ],
	'Freelance'  => [ 'Clear scope and regular communication', 'Prompt payment on invoice' ],
];

// ---------------------------------------------------------------------------
// Create them
// ---------------------------------------------------------------------------
$uploads = wp_upload_dir();
$n       = 0;

foreach ( $listings as $L ) {
	$html  = '<p>' . $L['intro'] . '</p>';
	$html .= '<h3>What you will do</h3><ul><li>' . implode( '</li><li>', $L['do'] ) . '</li></ul>';
	$html .= '<h3>What we are looking for</h3><ul><li>' . implode( '</li><li>', $L['need'] ) . '</li></ul>';
	if ( 'Full Time' === $L['type'] || 'Internship' === $L['type'] || 'Part Time' === $L['type'] ) {
		$html .= '<h3>What we offer</h3><ul><li>' . implode( '</li><li>', $offer[ $L['type'] ] ) . '</li></ul>';
	}
	if ( ! empty( $L['about'] ) ) {
		$html .= '<h3>About ' . $L['church'] . '</h3><p>' . $L['about'] . '</p>';
	}
	$html .= '<h3>How to apply</h3><p>Select <strong>Apply for job</strong> to send a cover letter and resume. ' . $L['church'] . ' is an equal opportunity ministry. Applications are reviewed as they arrive.</p>';
	if ( in_array( 'filled', $L['flags'], true ) ) {
		$html = '<p>' . $L['intro'] . '</p>';
	}

	$date = gmdate( 'Y-m-d H:i:s', time() - ( $L['days'] * DAY_IN_SECONDS ) - ( $n * 600 ) );
	$id   = wp_insert_post(
		[
			'post_title'    => $L['title'],
			'post_content'  => $html,
			'post_type'     => 'job_listing',
			'post_status'   => 'publish',
			'post_date'     => get_date_from_gmt( $date ),
			'post_date_gmt' => $date,
			'menu_order'    => in_array( 'featured', $L['flags'], true ) ? -1 : 0,
		]
	);

	wp_set_object_terms( $id, [ $term_ids['job_listing_type'][ $L['type'] ] ], 'job_listing_type' );
	wp_set_object_terms( $id, [ $term_ids['job_listing_category'][ $L['cat'] ] ], 'job_listing_category' );

	$slug = sanitize_title( $L['church'] );
	update_post_meta( $id, '_company_name', $L['church'] );
	update_post_meta( $id, '_company_website', $L['site'] );
	// Most apply by email; a few send people to the church's own application page.
	$apply_by_site = in_array( $L['title'], [ 'Lead Pastor', 'Executive Pastor', 'Worship Pastor', 'Communications Director', 'Campus Pastor', 'Facilities & Grounds Manager' ], true );
	update_post_meta( $id, '_application', $apply_by_site ? $L['site'] . '/careers/' . sanitize_title( $L['title'] ) : 'careers@' . $slug . '.example.org' );
	update_post_meta( $id, '_featured', in_array( 'featured', $L['flags'], true ) ? 1 : 0 );
	update_post_meta( $id, '_filled', in_array( 'filled', $L['flags'], true ) ? 1 : 0 );
	update_post_meta( $id, '_remote_position', in_array( 'remote', $L['flags'], true ) ? 1 : 0 );
	update_post_meta( $id, '_job_expires', gmdate( 'Y-m-d', time() + ( 30 * DAY_IN_SECONDS ) ) );

	// Salary: text, unit and optional currency.
	update_post_meta( $id, '_job_salary', $L['pay'][0] );
	update_post_meta( $id, '_job_salary_unit', $L['pay'][1] );
	update_post_meta( $id, '_job_salary_currency', isset( $L['pay'][2] ) ? $L['pay'][2] : '' );

	// Location the way the form saves it: raw values first, then the plugin's action.
	$loc = $L['loc'] + [ '', '', '' ];
	update_post_meta( $id, '_job_city', $loc[0] );
	update_post_meta( $id, '_job_state', $loc[1] );
	update_post_meta( $id, '_job_postcode', $loc[2] );
	do_action( 'job_manager_update_job_data', $id, [ 'job' => [ 'job_city' => $loc[0], 'job_state' => $loc[1], 'job_postcode' => $loc[2] ] ] );

	// A "full job description" PDF for some listings.
	if ( in_array( 'pdf', $L['flags'], true ) ) {
		$dir = $uploads['basedir'] . '/job-manager-uploads/job_description_pdf/sample';
		wp_mkdir_p( $dir );
		$file  = sanitize_title( $L['title'] ) . '-job-description.pdf';
		$lines = array_merge( [ 'Full job description', '' ], array_map( function ( $s ) { return '- ' . $s; }, array_merge( $L['do'], [ '' ], $L['need'] ) ) );
		file_put_contents( $dir . '/' . $file, $make_pdf( $L['title'], $L['church'], array_slice( $lines, 0, 24 ) ) );
		update_post_meta( $id, '_job_description_pdf', $uploads['baseurl'] . '/job-manager-uploads/job_description_pdf/sample/' . $file );
	}

	++$n;
}

echo "Created $n listings.\n";
