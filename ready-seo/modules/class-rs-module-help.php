<?php
/**
 * Ready Studio SEO Engine - Module: Help & Documentation
 *
 * v13.1: Rewritten for a public, commercial audience.
 * - Embedded worker.js code.
 * - Generalized all CPT-specific language.
 * - Added guidance on "minimum content".
 * - Rewrote troubleshooting to be customer-facing.
 *
 * @package   ReadyStudio
 * @version   13.1.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Module_Help {

	/**
	 * Constructor.
	 * Hooks into the Core Loader.
	 *
	 * @param ReadyStudio_Core_Loader $core_loader The main loader instance.
	 */
	public function __construct( $core_loader ) {
		// This module has no dependencies, so we just register its menu.
		add_action( 'admin_menu', [ $this, 'register_menu' ], 40 ); // 40 = last
	}

	/**
	 * 1. Registers the "Help" submenu page.
	 * Fired by 'admin_menu'.
	 */
	public function register_menu() {
		add_submenu_page(
			'promptseo_dashboard',      // Parent slug
			'راهنما و آموزش',          // Page Title
			'راهنما و آموزش',          // Menu Title
			'manage_options',           // Capability
			'promptseo_help',           // Menu Slug (unique)
			[ $this, 'render_page' ]    // Callback
		);
	}

	/**
	 * 2. Renders the HTML for the Help & Documentation page.
	 */
	public static function render_page() {
		?>
		<div class="wrap rs-wrap settings-page" dir="rtl">
			
			<div class="rs-header">
				<h1>
					AI SEO
					<span class="rs-brand-family">از خانواده <strong>Ready Studio</strong></span>
				</h1>
			</div>

			<!-- Tab Navigation for Help Page -->
			<nav class="rs-tabs" id="rs-help-tabs">
				<a href="#" class="rs-tab-link active" data-tab="tab-start">
					<span class="dashicons dashicons-dashboard" style="margin-left: 5px;"></span>
					شروع سریع
				</a>
				<a href="#" class="rs-tab-link" data-tab="tab-metabox">
					<span class="dashicons dashicons-edit-page" style="margin-left: 5px;"></span>
					کار در ویرایشگر (متاباکس)
				</a>
				<a href="#" class="rs-tab-link" data-tab="tab-bulk">
					<span class="dashicons dashicons-admin-media" style="margin-left: 5px;"></span>
					تولید انبوه
				</a>
				<a href="#" class="rs-tab-link" data-tab="tab-robots">
					<span class="dashicons dashicons-media-text" style="margin-left: 5px;"></span>
					سازنده Robots.txt
				</a>
				<a href="#" class="rs-tab-link" data-tab="tab-troubleshoot">
					<span class="dashicons dashicons-warning" style="margin-left: 5px;"></span>
					عیب‌یابی
				</a>
			</nav>

			<!-- Help Content -->
			<div class="rs-tab-content-wrapper" style="padding: 24px; background: #fff;">

				<!-- === TAB 1: QUICK START === -->
				<div id="tab-start" class="rs-tab-content active">
					<h2>شروع سریع: راه‌اندازی در ۳ مرحله</h2>
					<p>به افزونه AI SEO خوش آمدید. برای فعال‌سازی کامل، این ۳ مرحله ضروری هستند.</p>

					<h3 style="color: var(--rs-primary);">مرحله ۱: اتصال به Cloudflare و Gemini</h3>
					<ol>
						<li>
							<p>به حساب Cloudflare خود بروید، یک <strong>Worker</strong> جدید بسازید و کد زیر را در آن کپی کنید:</p>
							<details style="border: 1px solid var(--g-border); border-radius: 4px; margin: 10px 0;">
								<summary style="padding: 10px; cursor: pointer; font-weight: 600; color: var(--rs-primary);">
									[+] نمایش کد Cloudflare Worker (worker.js)
								</summary>
								<pre style="background: #010101; color: #f1f1f1; padding: 15px; border-radius: 0 0 4px 4px; margin: 0; overflow-x: auto; direction: ltr; text-align: left; font-family: var(--rs-font-code); font-size: 12px;">
export default {
  async fetch(request, env, ctx) {
    if (request.method !== "POST") {
      return new Response(JSON.stringify({ error: { message: "Expected POST request" } }), {
        status: 405,
        headers: { "Content-Type": "application/json" }
      });
    }

    try {
      const data = await request.json();
      const { api_key, model_name, action_type } = data;

      if (!api_key || !model_name) {
        return new Response(JSON.stringify({ error: { message: "Missing API Key or Model Name" } }), {
          status: 400,
          headers: { "Content-Type": "application/json" }
        });
      }

      let geminiUrl;
      let payload;

      if (action_type === 'vision') {
        geminiUrl = `https://generativelanguage.googleapis.com/v1beta/models/${model_name}:generateContent?key=${api_key}`;
        const { system_prompt, image_data, mime_type } = data;
        payload = {
          contents: [{
            parts: [
              { text: system_prompt },
              { inlineData: { mimeType: mime_type, data: image_data } }
            ]
          }],
          generationConfig: { responseMimeType: "application/json" }
        };
      } else { // 'text' or default
        geminiUrl = `https://generativelanguage.googleapis.com/v1beta/models/${model_name}:generateContent?key=${api_key}`;
        const { contents, generationConfig } = data;
        payload = {
          contents: contents,
          generationConfig: generationConfig
        };
      }

      const geminiResponse = await fetch(geminiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });

      const geminiData = await geminiResponse.json();

      if (!geminiResponse.ok) {
        throw new Error(geminiData.error.message || "Unknown Gemini API error");
      }

      return new Response(JSON.stringify(geminiData), {
        headers: { "Content-Type": "application/json" }
      });

    } catch (error) {
      return new Response(JSON.stringify({ error: { message: error.message } }), {
        status: 500,
        headers: { "Content-Type": "application/json" }
      });
    }
  }
};
								</pre>
							</details>
						</li>
						<li>به پیشخوان وردپرس بروید: <strong>Ready Studio -> تنظیمات</strong>.</li>
						<li>در تب "اتصال (API)"، <strong>آدرس ورکر</strong> (که در مرحله ۱ ساختید) و <strong>کلید API گوگل Gemini</strong> خود را وارد کنید.</li>
						<li>دکمه <strong>"تست ارتباط با ورکر"</strong> را بزنید.</li>
						<li>اگر پیام "✓ ارتباط موفقیت‌آمیز بود!" را دیدید، به مرحله بعد بروید.</li>
					</ol>

					<h3 style="color: var(--rs-primary);">مرحله ۲: آموزش "مغز هوش مصنوعی" (AI Brain)</h3>
					<p>این مهم‌ترین بخش است. AI برای تولید محتوای خوب، باید سایت شما را بشناسد.</p>
					<ol>
						<li>به تب <strong>"مغز هوش مصنوعی (AI Brain)"</strong> بروید.</li>
						<li><strong>دانش پایه:</strong> به AI بگویید سایت شما چیست و چه هویتی دارد.
							<br><em>(مثال: ما یک سایت فروشگاهی برای قهوه هستیم. مخاطبان ما جوان و حرفه‌ای هستند. لحن ما باید دوستانه و آموزشی باشد.)</em></li>
						<li><strong>پرامپت سفارشی:</strong> قوانین اجباری را تعیین کنید.
							<br><em>(مثال: در تمام توضیحات متا، کلمه "قهوه تازه" را بگنجان. هرگز از کلمات تبلیغاتی استفاده نکن.)</em></li>
						<li>تنظیمات را ذخیره کنید.</li>
					</ol>

					<h3 style="color: var(--rs-primary);">مرحله ۳: استفاده از افزونه</h3>
					<p>افزونه شما آماده است. اکنون می‌توانید:</p>
					<ul>
						<li>به <strong>ویرایش یک نوشته</strong> بروید و از "متاباکس AI SEO" برای تولید سئو و محتوا استفاده کنید.</li>
						<li>به <strong>Ready Studio -> تولید انبوه</strong> بروید تا تمام پست‌های قدیمی خود را با یک کلیک بهینه‌سازی کنید.</li>
					</ul>
				</div>

				<!-- === TAB 2: METABOX === -->
				<div id="tab-metabox" class="rs-tab-content">
					<h2>کار در ویرایشگر (متاباکس)</h2>
					<p>در صفحه ویرایش هر پست، برگه یا محصول، متاباکس "دستیار AI SEO" ظاهر می‌شود. این متاباکس دارای ۳ ماژول اصلی است:</p>
					
					<div class="cpt-notice" style="background-color: #fff8e1; color: #665200; border-right-color: #f9ab00;">
						<strong style="color: #000;">نکته مهم:</strong> برای بهترین نتیجه، هوش مصنوعی نیاز به "زمینه" (Context) دارد. قبل از کلیک روی دکمه‌های "تولید"، مطمئن شوید که حداقل یک **عنوان** و **چند خط محتوا** در ویرایشگر وردپرس نوشته‌اید تا AI بفهمد موضوع صفحه چیست.
					</div>

					<h4>۱. تب "سئو (SEO)"</h4>
					<p>این تب وظیفه تولید داده‌های اصلی سئو را دارد:</p>
					<ul>
						<li><strong>کلمه کلیدی کانونی:</strong> AI بهترین کلمه کلیدی را بر اساس محتوای شما پیدا می‌کند. (سازگار با Rank Math)</li>
						<li><strong>عنوان سئو:</strong> یک تیتر جذاب و بهینه برای گوگل.</li>
						<li><strong>توضیحات متا:</strong> متنی برای جذب کلیک در نتایج جستجو.</li>
						<li><strong>تگ‌ها:</strong> تگ‌های مرتبط با محتوا.</li>
						<li><strong>(اختیاری) نام لاتین:</strong> برای پست تایپ‌هایی که نیاز به اسلاگ (URL) انگلیسی تمیز دارند، این فیلد را پر می‌کند. (می‌توانید فعال/غیرفعال بودن آن را در صفحه "تولید انبوه" کنترل کنید).</li>
					</ul>

					<h4>۲. تب "محتوا"</h4>
					<p>این ماژول مشکل "محتوای ضعیف" (Thin Content) را حل می‌کند:</p>
					<ul>
						<li><strong>نویسنده هوشمند:</strong> با کلیک روی این دکمه، AI محتوای شما را می‌خواند و یک پاراگراف توصیفی کامل در مورد موضوع، سبک، و کاربردهای آن می‌نویسد. این متن در فیلد `in-content` ظاهر می‌شود.</li>
						<li><strong>متن جایگزین (Alt Text):</strong> یک متن Alt بر اساس *تحلیل متنی* شما تولید می‌کند.</li>
					</ul>

					<h4>۳. تب "تحلیل بصری" (Vision)</h4>
					<p>این قوی‌ترین ماژول افزونه است. این ماژول به جای خواندن *متن*، مستقیماً به **تصویر شاخص** شما نگاه می‌کند.</p>
					<ol>
						<li>ابتدا "تصویر شاخص" (Featured Image) پست را تنظیم و پست را ذخیره/پیش‌نویس کنید.</li>
						<li>به این تب بیایید. پیش‌نمایش تصویر را خواهید دید.</li>
						<li>دکمه "شروع تحلیل بصری" را بزنید.</li>
					</ol>
					<p>AI تصویر را "می‌بیند" و دقیق‌ترین اطلاعات ممکن را برمی‌گرداند. این اطلاعات (مخصوصاً **Alt Text**) معمولاً دقیق‌تر از تب "محتوا" هستند و به طور خودکار فیلدهای مربوطه را پر می‌کنند.</p>

					<h4>دکمه "ذخیره تمام تغییرات"</h4>
					<p>این دکمه اطلاعات را از **تمام** تب‌ها جمع‌آوری کرده و یک‌جا در دیتابیس (شامل فیلدهای Rank Math) ذخیره می‌کند.</p>
				</div>

				<!-- === TAB 3: BULK GENERATOR === -->
				<div id="tab-bulk" class="rs-tab-content">
					<h2>تولید انبوه (Bulk Generator)</h2>
					<p>این صفحه به شما اجازه می‌دهد تمام پست‌های قدیمی سایت را به صورت دسته‌ای و در صف (Queue) بهینه‌سازی کنید.</p>
					<ol>
						<li><strong>انتخاب پست تایپ:</strong> از منوی کشویی بالا، نوع پست‌هایی که می‌خواهید (مثلاً "نوشته‌ها"، "محصولات" یا پست تایپ سفارشی خود) را انتخاب کنید.</li>
						<li><strong>انتخاب عملیات:</strong> مشخص کنید AI چه کارهایی انجام دهد (تولید سئو، تولید محتوا، تولید Alt).</li>
						<li><strong>انتخاب تنظیمات:</strong> تنظیمات خاص (مانند "عنوان دقیق" یا "آپدیت اسلاگ") را فعال کنید.
							<br><em>(نکته: "عنوان دقیق" به AI می‌گوید به جای تیتر جذاب، یک تیتر توصیفی بسازد که برای گالری‌ها یا لیست محصولات مناسب است).</em>
						</li>
						<li><strong>انتخاب پست‌ها:</strong> پست‌های مورد نظر را از جدول تیک بزنید (یا تیک "انتخاب همه" را بزنید).</li>
						<li><strong>شروع پردازش:</strong> دکمه سبز "شروع پردازش" را بزنید.</li>
					</ol>
					<p>عملیات در "کنسول لاگ" (جعبه سیاه) نمایش داده می‌شود. اگر با خطای "خطای فاجعه‌بار (سرور)" مواجه شدید، معمولاً به معنای تمام شدن زمان اجرای PHP روی هاست شماست (Timeout). سرور خود را بررسی کنید یا تعداد کمتری پست را همزمان پردازش کنید.</p>
				</div>

				<!-- === TAB 4: ROBOTS.TXT === -->
				<div id="tab-robots" class="rs-tab-content">
					<h2>سازنده هوشمند Robots.txt</h2>
					<p>این ماژول به شما اجازه می‌دهد فایل `robots.txt` سایت خود را با هوش مصنوعی مدیریت کنید.</p>
					
					<h4>پرامپت سفارشی (دستور به AI)</h4>
					<p>این بخش اصلی ماژول است. شما می‌توانید با زبان فارسی به AI دستور دهید چه قوانینی بسازد:</p>
					<ul>
						<li><em>مثال ۱: "یک فایل استاندارد وردپرس بساز که پوشه آپلودها باز باشد."</em></li>
						<li><em>مثال ۲: "سایت را از دسترس تمام ربات‌ها خارج کن."</em></li>
						<li><em>مثال ۳: "همه ربات‌ها را ببند بجز گوگل، و اجازه ایندکس تصاویر را هم بده."</em></li>
					</ul>
					<p>پس از نوشتن دستور، دکمه "اجرای پرامپت" را بزنید. AI قوانین را در کادر "قوانین تولید شده توسط AI" می‌نویسد.</p>

					<h4>مجازی در مقابل فیزیکی (حل خطای 404)</h4>
					<p>این افزونه به دو روش کار می‌کند:</p>
					<ol>
						<li><strong>حالت مجازی (پیش‌فرض):</strong> با زدن دکمه سبز "ذخیره تنظیمات (فایل مجازی)"، افزونه قوانین شما را در دیتابیس ذخیره می‌کند و به صورت مجازی به ربات‌ها نشان می‌دهد. (این روش پیشنهادی وردپرس است).</li>
						<li><strong>حالت فیزیکی (حل مشکل 404):</strong> اگر آدرس `yoursite.com/robots.txt` خطای 404 می‌دهد، یعنی سرور شما برای فایل فیزیکی تنظیم شده.
							<br>در این حالت، پس از تولید قوانین و اطمینان از "پیش‌نمایش نهایی"، دکمه مشکی "نوشتن فایل فیزیکی در هاست" را بزنید. افزونه فایل `robots.txt` را مستقیماً در ریشه هاست شما می‌نویسد.</li>
					</ol>
					<p><strong>خطای دسترسی (Permission Error):</strong> اگر هنگام "نوشتن فایل فیزیکی" با خطا مواجه شدید، به این معناست که PHP اجازه نوشتن در ریشه هاست شما را ندارد. باید دسترسی (Permission) فایل `robots.txt` (اگر وجود دارد) یا پوشه ریشه (root) را به `644` یا `755` تغییر دهید.</p>
				</div>
				
				<!-- === TAB 5: TROUBLESHOOTING (CUSTOMER-FACING) === -->
				<div id="tab-troubleshoot" class="rs-tab-content">
					<h2>عیب‌یابی و خطاهای رایج</h2>

					<h4>خطا: "✗ خطا: Error: Missing API Key or Model Name"</h4>
					<p><strong>علت:</strong> این خطا از طرف ورکر کلودفلر می‌آید.
					<br><strong>راه حل:</strong> به <strong>Ready Studio -> تنظیمات -> اتصال (API)</strong> بروید و مطمئن شوید که "کلید API (Gemini)" را به درستی وارد کرده‌اید.</p>

					<h4>خطا: "✗ خطا: Error: 404 Not Found" (یا خطاهای 4xx/5xx دیگر از Gemini)</h4>
					<p><strong>علت:</strong> کلید API شما صحیح است، اما مدل AI (مثلاً `gemini-2.0-flash`) برای اکانت شما فعال نیست، خطا دارد، یا درخواست شما توسط گوگل رد شده است.
					<br><strong>راه حل:</strong> در صفحه "اتصال (API)"، "مدل هوش مصنوعی" را به `gemini-1.5-pro` تغییر دهید و مجدداً تست کنید. اگر مشکل ادامه داشت، وضعیت API خود را در پنل Google AI Studio بررسی کنید.</p>

					<h4>خطا: "✗ خطای سرور. (AJAX Fail)" (در صفحه تنظیمات)</h4>
					<p><strong>علت:</strong> سرور وردپرس شما نتوانست درخواست AJAX را پردازش کند. این معمولاً به دلیل تداخل یک افزونه امنیتی (که REST API را مسدود کرده) یا تنظیمات امنیتی هاست شما (ModSecurity) است.
					<br><strong>راه حل:</strong> افزونه‌های امنیتی خود را موقتاً غیرفعال کنید و مجدداً تست کنید. اگر مشکل حل شد، در افزونه امنیتی خود، `admin-ajax.php` را در لیست سفید قرار دهید.</p>
					
					<h4>مشکل: منوهای افزونه (مثل Robots.txt) نمایش داده نمی‌شوند.</h4>
					<p><strong>علت:</strong> این اتفاق زمانی رخ می‌دهد که فایل‌های افزونه به درستی کپی نشده باشند یا یک فایل ماژول (`/modules/`) حذف شده باشد.
					<br><strong>راه حل:</strong> افزونه را به طور کامل حذف و مجدداً از ابتدا نصب کنید تا مطمئن شوید تمام فایل‌ها سر جای خود هستند.</p>

					<h4>مشکل: متاباکس در ویرایش پست خالی است (فقط دکمه ذخیره دارد).</h4>
					<p><strong>علت:</strong> مشابه خطای قبلی، فایل‌های ماژول (`/modules/`) به درستی بارگذاری نشده‌اند.
					<br><strong>راه حل:</strong> افزونه را به طور کامل حذف و مجدداً نصب کنید. مطمئن شوید که پوشه `modules` و تمام فایل‌های داخل آن (`class-rs-module-seo.php`، `class-rs-module-content.php` و `class-rs-module-vision.php`) به درستی در هاست شما کپی شده‌اند.</p>
				</div>
				
			</div>
		</div>

		<!-- Script for tab switching on this page -->
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				var $tabs = $('#rs-help-tabs .rs-tab-link');
				var $contents = $('.rs-tab-content-wrapper .rs-tab-content');

				$tabs.click(function(e) {
					e.preventDefault();
					var targetTab = $(this).data('tab');

					$tabs.removeClass('active');
					$(this).addClass('active');

					$contents.removeClass('active');
					$('#' + targetTab).addClass('active');
				});
			});
		</script>
		<?php
	}

} // End class ReadyStudio_Module_Help

// Instantiate the module by hooking into the core loader
add_action( 'rs_core_loaded', function( $core_loader ) {
	new ReadyStudio_Module_Help( $core_loader );
} );