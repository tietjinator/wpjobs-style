#!/usr/bin/env python3
"""Convert the old job board CSV export into WordPress WXR files for WP Job Manager.

Usage:
  python3 tools/csv-to-wxr.py jobs.csv OUT_DIR [--since 2026-09-05] [--today 2026-11-05]
         [--unlisted draft|private|skip] [--chunk 300] [--tz America/Indiana/Indianapolis]

Writes OUT_DIR/wesleyan-jobs-01.xml, -02.xml ... and OUT_DIR/import-report.csv.
Standard library only. State names come from includes/location-fields.php so
the importer and the plugin always agree.
"""
import argparse
import csv
import html as htmllib
import os
import re
import sys
from collections import Counter
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

csv.field_size_limit(10_000_000)
HERE = os.path.dirname(os.path.abspath(__file__))

ap = argparse.ArgumentParser()
ap.add_argument('csv')
ap.add_argument('out')
ap.add_argument('--since', default=None, help='only listings starting on/after this date (default: 2 months before --today)')
ap.add_argument('--today', default=datetime.now().strftime('%Y-%m-%d'))
ap.add_argument('--unlisted', default='draft', choices=['draft', 'private', 'skip'])
ap.add_argument('--chunk', type=int, default=300)
ap.add_argument('--tz', default='America/Indiana/Indianapolis')
args = ap.parse_args()

today = datetime.strptime(args.today, '%Y-%m-%d')
since = args.since or (today - timedelta(days=61)).strftime('%Y-%m-%d')
tz = ZoneInfo(args.tz)
utc = ZoneInfo('UTC')

# ---- state map, read from the plugin
src = open(os.path.join(HERE, '..', 'includes', 'location-fields.php'), encoding='utf-8').read()
block = src[src.index('function wpjm_cs_state_map'):]
block = block[:block.index('];')]
STATE_MAP = dict(re.findall(r"'([^']+)'\s*=>\s*'([A-Z]{2})'", block))
CODE_NAME = {}
for name, code in STATE_MAP.items():
    if code not in CODE_NAME:
        p = name.title().replace(' And ', ' and ').replace(' Of ', ' of ').replace('U.S.', 'U.S.').replace('U.s.', 'U.S.')
        CODE_NAME[code] = p
CODES = set(CODE_NAME)

COUNTRY = {'USA': 'United States', 'CAN': 'Canada', 'JAM': 'Jamaica', 'PRI': 'Puerto Rico', 'AUS': 'Australia', 'NZL': 'New Zealand'}

CATS = [
    ('Worship & Creative Arts', r'worship|music|choir|organist|pianist|creative|audio|\bband\b|vocal|sound'),
    ('Children & Youth', r'child|kid|youth|student|family|families|nursery|preschool|pre-school|next ?gen|teen|camp\b|early childhood|young adult|college|campus'),
    ('Communications & Media', r'communicat|media|marketing|video|graphic|\bweb\b|social|editor|writer|digital|design'),
    ('Administration & Operations', r'administrat|office|secretar|bookkeep|account|financ|operation|business manager|facilit|custodia|maintenance|reception|\bhr\b|human resources|director of finance|treasur|assistant'),
    ('Missions & Outreach', r'mission|outreach|compassion|evangel|church plant|planter|community|chaplain|global|multicultural|international'),
    ('Pastoral & Leadership', r'pastor|minister|ministry|superintendent|president|leader|elder|disciple|teaching|counsel|care|residen|director|chaplain|dean|professor|campus'),
]
CAT_RES = [(n, re.compile(p, re.I)) for n, p in CATS]


def category(title):
    for name, rx in CAT_RES:
        if rx.search(title):
            return name
    return 'Other'


def slug(s):
    return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')


def cdata(s):
    return '<![CDATA[' + s.replace(']]>', ']]]]><![CDATA[>') + ']]>'


def clean_title(t):
    t = htmllib.unescape(t)
    return re.sub(r'\s+', ' ', t).strip()


def clean_html(h):
    h = h.replace('\r\n', '\n').replace('\r', '\n').replace(' ', ' ').replace('&nbsp;', ' ')
    h = re.sub(r'<(/?)b(\s[^>]*)?>', r'<\1strong>', h, flags=re.I)
    h = re.sub(r'<(/?)i(\s[^>]*)?>', r'<\1em>', h, flags=re.I)
    h = re.sub(r'<(/?)h[3-6]\b[^>]*>', r'<\1h3>', h, flags=re.I)
    h = re.sub(r'<(\w+)([^>]*)\sstyle="[^"]*"', r'<\1\2', h)
    h = re.sub(r'(?i)<br\s*/?>\s*$', '', h.strip())
    # drop empty paragraphs and collapse the tab indentation the old editor added
    for _ in range(2):
        h = re.sub(r'<(p|strong|em|h3|li)>\s*</\1>', '', h, flags=re.I)
    h = re.sub(r'\n[ \t]+', '\n', h)
    h = re.sub(r'[ \t]{2,}', ' ', h)
    h = re.sub(r'\n{3,}', '\n\n', h)
    return h.strip()


def norm_zip(z, country, state):
    z = z.strip().strip('`\'"').upper()
    notes = []
    if country == 'CAN' or (state and state in {'AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'}):
        m = re.sub(r'[^A-Z0-9]', '', z.replace('NB', '', 1) if z.startswith('NB') else z)
        m = m.replace('KOG', 'K0G')
        if re.fullmatch(r'[A-Z]\d[A-Z]\d[A-Z]\d', m):
            return m[:3] + ' ' + m[3:], notes
        return '', ['unusable postal code: ' + z]
    d = re.sub(r'[^0-9-]', '', z)
    if re.fullmatch(r'\d{5}(-\d{4})?', d):
        return d, notes
    if re.fullmatch(r'\d{4}', d):
        notes.append('zip padded with leading zero')
        return '0' + d, notes
    if z:
        notes.append('unusable zip: ' + z)
    return '', notes


def parse_email(v):
    v = v.strip()
    if not v:
        return '', ['no application contact']
    if re.match(r'^https?://', v):
        return v, []
    found = re.findall(r'[^\s,;<>()]+@[^\s,;<>()]+\.[A-Za-z]{2,}', v)
    if not found:
        return '', ['application contact is not an email or link: ' + v[:60]]
    notes = []
    if len(found) > 1 or found[0] != v:
        notes.append('contact cleaned to first address: ' + found[0])
    return found[0], notes


def types(r):
    t = []
    jt, ty = r['job_time'].strip(), r['job_type'].strip()
    if jt == 'full':
        t.append('Full Time')
    elif jt == 'part':
        t.append('Part Time')
    if ty in ('intern', 'residency') or jt in ('intern', 'residency'):
        t.append('Internship')
    if r['job_temporary'].strip().lower() == 'true':
        t.append('Temporary')
    return t


rows = list(csv.DictReader(open(args.csv, newline='', encoding='utf-8-sig')))
os.makedirs(args.out, exist_ok=True)

items, report = [], []
skipped = Counter()
for r in rows:
    start = r['starts_display_at'].strip()
    if start[:10] < since:
        skipped['older than ' + since] += 1
        continue
    if r['status'] == 'unlisted' and args.unlisted == 'skip':
        skipped['unlisted'] += 1
        continue

    notes = []
    title = clean_title(r['job_title'])
    end = r['ends_display_at'].strip()[:10]
    ended = bool(end) and end < args.today
    if r['status'] == 'unlisted':
        status = args.unlisted
        notes.append('was unlisted')
    else:
        status = 'expired' if ended else 'publish'

    country = r['job_country'].strip()
    st = (r['job_state'].strip() or r['job_province'].strip()).upper()
    city = r['job_city'].strip()
    if st and st not in CODES:
        notes.append('unknown state/province: ' + st)
        st = ''
    zipc, zn = norm_zip(r['job_zip'], country, st)
    notes += zn
    if not st:
        notes.append('no state/province')
    if not city:
        notes.append('no city')

    if st:
        loc = ', '.join(x for x in [city, st] if x)
        if zipc:
            loc += ' ' + zipc
        split = True
    else:
        loc = ', '.join(x for x in [city, COUNTRY.get(country, country)] if x)
        split = False

    app, an = parse_email(r['job_email'])
    notes += an
    if not r['job_organization'].strip():
        notes.append('no organisation')
    ts = types(r)
    if not ts:
        notes.append('no job type')
    body = clean_html(r['html'])
    if len(re.sub(r'<[^>]+>', '', body)) < 40:
        notes.append('very short description')

    cat = category(title)
    local = datetime.strptime(start, '%Y-%m-%d %H:%M:%S').replace(tzinfo=tz)
    gmt = local.astimezone(utc)

    meta = {
        '_filled': '0', '_featured': '0', '_job_location': loc, '_application': app,
        '_company_name': r['job_organization'].strip(), '_company_website': '',
        '_job_expires': end, '_remote_position': '0',
        '_job_city': city if split else '', '_job_state': st if split else '', '_job_postcode': zipc if split else '',
        '_wpjm_cs_location_split': '1' if split else '',
        '_legacy_id': r['id'], '_legacy_contact_name': r['job_contact_name'].strip(),
        '_legacy_phone': r['job_phone'].strip(), '_legacy_address': r['job_address'].strip(),
        '_legacy_wesleyan_type': r['job_wesleyan_type'].strip(), '_legacy_job_type': r['job_type'].strip(),
    }
    if split:
        meta['geolocation_state_short'] = st
        meta['geolocation_state_long'] = '%s (%s)' % (CODE_NAME[st], st)
    meta = {k: v for k, v in meta.items() if v != '' or k in ('_application', '_job_expires')}

    items.append(dict(id=r['id'], title=title, body=body, status=status, local=local, gmt=gmt,
                      types=ts, cat=cat, meta=meta))
    report.append([r['id'], title, status, start[:10], end, cat, ', '.join(ts), loc, app, '; '.join(notes)])

# ---- write WXR
HEAD = '''<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:wp="http://wordpress.org/export/1.2/"
>
<channel>
	<title>Wesleyan Jobs</title>
	<link>https://jobs.wesleyan.org</link>
	<description>Imported from the old job board</description>
	<language>en-US</language>
	<wp:wxr_version>1.2</wp:wxr_version>
	<wp:base_site_url>https://jobs.wesleyan.org</wp:base_site_url>
	<wp:base_blog_url>https://jobs.wesleyan.org</wp:base_blog_url>
	<wp:author><wp:author_id>1</wp:author_id><wp:author_login><![CDATA[wesjobs]]></wp:author_login><wp:author_email><![CDATA[it.web.services@wesleyan.org]]></wp:author_email><wp:author_display_name><![CDATA[IT Admin]]></wp:author_display_name><wp:author_first_name><![CDATA[]]></wp:author_first_name><wp:author_last_name><![CDATA[]]></wp:author_last_name></wp:author>
'''
TYPE_SLUG = {'Full Time': 'full-time', 'Part Time': 'part-time', 'Internship': 'internship', 'Temporary': 'temporary', 'Freelance': 'freelance'}


def item_xml(i):
    o = ['\t<item>',
         '\t\t<title>%s</title>' % cdata(i['title']),
         '\t\t<dc:creator>%s</dc:creator>' % cdata('wesjobs'),
         '\t\t<guid isPermaLink="false">legacy-job-%s</guid>' % i['id'],
         '\t\t<description></description>',
         '\t\t<content:encoded>%s</content:encoded>' % cdata(i['body']),
         '\t\t<excerpt:encoded>%s</excerpt:encoded>' % cdata(''),
         '\t\t<wp:post_id>%s</wp:post_id>' % i['id'],
         '\t\t<wp:post_date>%s</wp:post_date>' % cdata(i['local'].strftime('%Y-%m-%d %H:%M:%S')),
         '\t\t<wp:post_date_gmt>%s</wp:post_date_gmt>' % cdata(i['gmt'].strftime('%Y-%m-%d %H:%M:%S')),
         '\t\t<wp:comment_status>%s</wp:comment_status>' % cdata('closed'),
         '\t\t<wp:ping_status>%s</wp:ping_status>' % cdata('closed'),
         '\t\t<wp:post_name>%s</wp:post_name>' % cdata(slug(i['title'])[:60] + '-' + i['id']),
         '\t\t<wp:status>%s</wp:status>' % cdata(i['status']),
         '\t\t<wp:post_parent>0</wp:post_parent>',
         '\t\t<wp:menu_order>0</wp:menu_order>',
         '\t\t<wp:post_type>%s</wp:post_type>' % cdata('job_listing'),
         '\t\t<wp:post_password>%s</wp:post_password>' % cdata(''),
         '\t\t<wp:is_sticky>0</wp:is_sticky>']
    for t in i['types']:
        o.append('\t\t<category domain="job_listing_type" nicename="%s">%s</category>' % (TYPE_SLUG[t], cdata(t)))
    o.append('\t\t<category domain="job_listing_category" nicename="%s">%s</category>' % (slug(i['cat']), cdata(i['cat'])))
    for k, v in i['meta'].items():
        o.append('\t\t<wp:postmeta><wp:meta_key>%s</wp:meta_key><wp:meta_value>%s</wp:meta_value></wp:postmeta>' % (cdata(k), cdata(v)))
    o.append('\t</item>')
    return '\n'.join(o)


items.sort(key=lambda i: i['local'])
files = 0
for n in range(0, len(items), args.chunk):
    files += 1
    path = os.path.join(args.out, 'wesleyan-jobs-%02d.xml' % files)
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write(HEAD)
        fh.write('\n'.join(item_xml(i) for i in items[n:n + args.chunk]))
        fh.write('\n</channel>\n</rss>\n')

with open(os.path.join(args.out, 'import-report.csv'), 'w', newline='', encoding='utf-8') as fh:
    w = csv.writer(fh)
    w.writerow(['old_id', 'title', 'new_status', 'starts', 'ends', 'category', 'types', 'location', 'apply_via', 'notes'])
    w.writerows(report)

print('window: starts on/after', since, '| today', args.today)
print('read', len(rows), '| converted', len(items), '| skipped', dict(skipped), '| files', files)
print('status:', dict(Counter(i['status'] for i in items)))
print('categories:', dict(Counter(i['cat'] for i in items)))
print('types:', dict(Counter(t for i in items for t in i['types'])), '| none:', sum(1 for i in items if not i['types']))
print('with notes:', sum(1 for r in report if r[-1]))
