"""Read-only HTTP regression checks against local Apache; never prints employee data."""
import json
import urllib.error
import urllib.request

BASE = 'http://127.0.0.1/E2E_Tracking'
ALLOWED = ['https://beedatatechnology.com', 'https://beedatatech.com']

def request(path, origin=None, method='GET', extra=None):
    headers = dict(extra or {})
    if origin is not None:
        headers['Origin'] = origin
    req = urllib.request.Request(BASE + path, headers=headers, method=method)
    try:
        response = urllib.request.urlopen(req, timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.headers, response.read()

def check(condition, message):
    if not condition:
        raise AssertionError(message)

for origin in [None, 'null', 'https://evil.example', 'http://beedatatech.com',
               'https://beedatatech.com.evil.example', 'https://evilbeedatatech.com',
               'https://beedatatech.com:8443', 'https://beedatatech.com@evil.example']:
    for path in ['/public/employees', '/public/employees/1/photo']:
        status, headers, body = request(path, origin)
        check(status == 403 and not headers.get('Access-Control-Allow-Origin'), 'Unapproved origin bypassed access policy')

for origin in ALLOWED:
    status, headers, body = request('/public/employees?limit=1', origin)
    check(status == 200, 'Approved employee request failed')
    check(headers.get('Access-Control-Allow-Origin') == origin and headers.get('Vary') == 'Origin', 'CORS headers incorrect')
    check(not headers.get('Access-Control-Allow-Credentials'), 'Public API unexpectedly accepts browser credentials')
    payload = json.loads(body)
    check(payload['success'] and len(payload['data']) <= 1, 'Pagination response incorrect')
    allowed_fields = {'employee_id', 'name', 'role', 'profile_image_url', 'is_working',
                      'employment_status', 'date_of_joining', 'date_of_leaving', 'tenure', 'recorded_workdays'}
    for employee in payload['data']:
        check(set(employee) == allowed_fields, 'Unexpected fields in public payload')
        if employee['profile_image_url']:
            status, image_headers, image = request(employee['profile_image_url'].removeprefix('/E2E_Tracking'), origin)
            check(status in (200, 404), 'Photo route failed')
            if status == 200:
                check(image_headers.get_content_type() in ('image/jpeg', 'image/png', 'image/webp') and len(image) > 0, 'Photo bytes invalid')
    status, headers, body = request('/public/employees', origin, 'OPTIONS', {'Access-Control-Request-Method': 'GET', 'Access-Control-Request-Headers': 'content-type'})
    check(status == 204 and not body, 'Approved preflight failed')
    check(request('/public/employees', origin, 'OPTIONS', {'Access-Control-Request-Method': 'POST'})[0] == 403, 'Write preflight accepted')
    check(request('/public/employees', origin, 'OPTIONS', {'Access-Control-Request-Method': 'GET', 'Access-Control-Request-Headers': 'authorization'})[0] == 403, 'Unexpected header preflight accepted')
    check(request('/public/employees', origin, 'POST')[0] == 405, 'Public write accepted')
    check(request('/public/employees?limit=101', origin)[0] == 422, 'Invalid limit accepted')
    check(request('/public/employees?employee_id=%27%20OR%201%3D1%20--', origin)[0] == 200, 'Unsafe filter failed rather than returning no matches')
    check(request('/public/employees/2147483647/photo', origin)[0] == 404, 'Missing photo route incorrect')
    check(request('/public/employees/invalid/photo', origin)[0] == 404, 'Invalid photo route accepted')

check(request('/public/employees', None, 'OPTIONS', {'Access-Control-Request-Method': 'GET'})[0] == 403, 'Global OPTIONS bypassed policy')
check(request('/employees', 'http://localhost:3000')[0] == 401, 'Existing employee route lost authentication')
check(request('/employees', 'http://localhost:3000', 'OPTIONS')[0] == 200, 'Existing frontend preflight changed')
print('PASS HTTP directory, image access policy, origin rejection, preflight, methods, filters, pagination, private-field exclusion, existing protected routes.')