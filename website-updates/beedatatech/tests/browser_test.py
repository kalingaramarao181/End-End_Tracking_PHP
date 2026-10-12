from pathlib import Path
import sys,os,json,base64,urllib.request
sys.path.insert(0,str(Path(os.environ['TEMP'])/'e2e-beedatatech-site-work/tools'))
from playwright.sync_api import sync_playwright
root=Path.cwd()/'website-updates/beedatatech'
work=Path(os.environ['TEMP'])/'e2e-beedatatech-site-work'
logo=urllib.request.urlopen('https://beedatatech.com/home_images/beedata_logo.png',timeout=30).read()
(work/'beedata_logo.png').write_bytes(logo)
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII=')
fixtures=[]
for i in range(133):
 working=i%3==0
 fixtures.append({'employee_id':f'BDT-I-{i+1:03d}','name':f'Employee {i+1:03d}','role':['HR','IT Recruiter','Web Developer',None][i%4],'profile_image_url':f'/public/employees/{i+1}/photo' if i%5==0 else None,'is_working':working,'employment_status':'working' if working else 'left' if i%3==1 else 'not_working','date_of_joining':'2024-01-01','date_of_leaving':None if working or i%3==2 else '2025-01-01','tenure':{'calendar_days':700 if working else 366,'years':1,'months':0,'days':0} if working or i%3==1 else None,'recorded_workdays':i+1})
fixtures[0]['name']='Alex <img src=x onerror=alert(1)>'
state={'failed':False,'photo_failed':False,'pages':[],'origins':[]}
errors=[]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=r'C:\Program Files\Google\Chrome\Application\chrome.exe',headless=True)
 context=browser.new_context(viewport={'width':1440,'height':1100},device_scale_factor=1)
 page=context.new_page();page.on('pageerror',lambda error:errors.append(str(error)))
 def route(request):
  url=request.request.url
  from urllib.parse import urlparse,parse_qs
  parsed=urlparse(url)
  if parsed.hostname=='e2e.beedatatech.com':
   origin=request.request.headers.get('origin');state['origins'].append(origin)
   headers={'Access-Control-Allow-Origin':'https://beedatatech.com','Content-Type':'application/json'}
   if parsed.path.endswith('/photo'):
    request.fulfill(status=404 if state['photo_failed'] else 200,body=b'' if state['photo_failed'] else png,headers={'Access-Control-Allow-Origin':'https://beedatatech.com','Content-Type':'image/png'});return
   if state['failed']:request.fulfill(status=404,body='{"success":false}',headers=headers);return
   params=parse_qs(parsed.query);limit=int(params.get('limit',['100'])[0]);number=int(params.get('page',['1'])[0]);state['pages'].append(number)
   body={'success':True,'total':len(fixtures),'total_pages':(len(fixtures)+limit-1)//limit,'page':number,'limit':limit,'as_of_date':'2026-10-09','timezone':'America/New_York','data':fixtures[(number-1)*limit:number*limit]}
   request.fulfill(status=200,body=json.dumps(body),headers=headers);return
  if parsed.hostname=='beedatatech.com':
   if parsed.path in ['/','/index.html']:request.fulfill(status=200,body=(work/'published-home.html').read_bytes(),content_type='text/html');return
   if parsed.path=='/employees.html':request.fulfill(status=200,body=(root/'employees.html').read_bytes(),content_type='text/html');return
   name=parsed.path.rsplit('/',1)[-1]
   if name=='beedata_logo.png':request.fulfill(status=200,body=logo,content_type='image/png');return
   local=root/'UpdatedFiles'/name
   if not local.exists():local=work/name
   if local.exists() and local.is_file() and name.endswith(('.js','.css')):request.fulfill(status=200,body=local.read_bytes(),content_type='text/javascript' if name.endswith('.js') else 'text/css');return
   request.fulfill(status=200,body=png,content_type='image/png');return
  request.fulfill(status=200,body='',content_type='text/css')
 page.route('**/*',route)
 def ready():page.wait_for_function("document.querySelector('#totalCount').textContent === '133'")
 page.goto('https://beedatatech.com/employees.html');ready()
 assert page.locator('#workingCount').inner_text()=='45'
 assert page.locator('#formerCount').inner_text()=='88'
 assert set(state['pages'])=={1,2}
 assert page.locator('.employee-card').count()==12
 assert page.locator('.top-bar .right a').count()==4
 assert page.locator('.nav-links > li').count()==9
 page.locator('.nav-trigger').first.click();assert page.locator('.nav-trigger').first.get_attribute('aria-expanded')=='true'
 assert page.locator('.menu-open .dropdown-content').is_visible()
 page.keyboard.press('Escape');assert page.locator('.nav-trigger').first.get_attribute('aria-expanded')=='false'
 assert page.locator('.employee-name').first.inner_text()=='Alex <img src=x onerror=alert(1)>'
 assert page.locator('.employee-name img').count()==0
 assert not page.evaluate('document.documentElement.scrollWidth > innerWidth')
 page.screenshot(path=str(work/'directory-desktop.png'),full_page=True)
 page.locator('#loadMoreButton').click();assert page.locator('.employee-card').count()==24

 assert page.locator('#sortOrder').input_value()=='working_first'
 cards=page.locator('.employee-card').evaluate_all("items => items.map(e => e.dataset.working)")
 assert cards==sorted(cards,reverse=True),cards
 page.evaluate('window.scrollTo(0,700)');page.wait_for_timeout(500)
 assert abs(page.locator('.site-header').bounding_box()['y'])<1
 page.locator('[data-view="list"]').click()
 assert page.locator('[data-view="list"]').get_attribute('aria-pressed')=='true'
 assert page.locator('.employee-grid').evaluate("e => e.classList.contains('list-view')")
 assert not page.evaluate('document.documentElement.scrollWidth > innerWidth')
 page.set_viewport_size({'width':390,'height':844})
 assert not page.evaluate('document.documentElement.scrollWidth > innerWidth')
 page.locator('[data-view="grid"]').click()
 assert page.locator('[data-view="grid"]').get_attribute('aria-pressed')=='true'
 page.set_viewport_size({'width':1440,'height':1000});page.evaluate('window.scrollTo(0,0)')
 page.locator('.metric[data-status="working"]').click();assert '45 employees' in page.locator('#resultCount').inner_text();assert page.locator('.employee-card').count()==12
 page.locator('#roleFilter').select_option('role:HR');assert '12 employees' in page.locator('#resultCount').inner_text()
 page.locator('#employeeSearch').fill('BDT-I-013');assert page.locator('.employee-card').count()==1
 page.locator('.employee-details summary').click();assert page.locator('.details-list').is_visible()
 page.locator('#employeeSearch').fill('no employee matches this');assert page.locator('#feedback h3').inner_text()=='No employees match your filters.'
 page.locator('.feedback-button').click();assert page.locator('.employee-card').count()==12
 page.locator('#sortOrder').select_option('name_desc');assert page.locator('.employee-name').first.inner_text()=='Employee 133'
 page.set_viewport_size({'width':390,'height':844});page.locator('.menu-toggle').click();assert page.locator('#siteNavigation').is_visible();page.locator('.nav-trigger').first.click();assert page.locator('.menu-open .dropdown-content').is_visible();page.locator('.menu-toggle').click();assert not page.locator('#siteNavigation').is_visible();page.locator('body').click(position={'x':2,'y':2});page.evaluate('window.scrollTo(0,0)');page.screenshot(path=str(work/'directory-mobile.png'),full_page=True)
 assert not page.evaluate('document.documentElement.scrollWidth > innerWidth')
 assert page.locator('.employee-card').first.bounding_box()['width']>300
 page.set_viewport_size({'width':768,'height':1024});assert not page.evaluate('document.documentElement.scrollWidth > innerWidth')
 state['failed']=True;page.locator('#refreshButton').click();page.wait_for_selector('.feedback.unavailable');assert page.locator('#totalCount').inner_text()=='—';assert page.locator('.employee-card').count()==0
 page.screenshot(path=str(work/'directory-unavailable.png'),full_page=True)
 state['failed']=False;page.locator('.feedback-button').click();ready()
 state['photo_failed']=True;page.reload();ready();page.wait_for_timeout(500)
 page.locator('.portrait img').evaluate_all("images => images.forEach(image => image.loading = 'eager')")
 page.wait_for_function("document.querySelectorAll('.portrait img').length === 0")
 assert page.locator('.portrait img').count()==0
 assert page.locator('.initials').first.is_visible()
 assert page.evaluate("window.BeeDataEmployees.photoURL('https://evil.example/public/employees/1/photo')") is None
 assert all(origin=='https://beedatatech.com' for origin in state['origins'])
 page.set_viewport_size({'width':1440,'height':1100});page.goto('https://beedatatech.com/');page.wait_for_function("document.querySelector('#employeesCount').textContent === '133'")
 assert page.locator('.employee-counter-link').get_attribute('href')=='/employees.html'
 page.locator('.employee-counter-link').scroll_into_view_if_needed();page.screenshot(path=str(work/'homepage-counter.png'))
 page.locator('.employee-counter-link').click();ready()
 state['failed']=True;page.goto('https://beedatatech.com/');page.wait_for_selector('.employee-counter-link');page.wait_for_timeout(500)
 assert page.locator('#employeesCount').inner_text()=='—'
 assert not errors,errors
 browser.close()
print('PASS 133-employee pagination, live homepage total/link, filters, search, sorting, details, load more, private image CORS/fallback, XSS escaping, retry, unavailable API, desktop/mobile/tablet layouts; no uncaught JavaScript errors.')