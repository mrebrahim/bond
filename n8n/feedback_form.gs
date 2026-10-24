/**
 * فورم تقييم الريل (PRD §7) — script.google.com ← New project ← الصق ← Run ‏createBondFeedbackForm
 * بيعمل الفورم + شيت الردود، وبيطبع القيم اللي بتتحط في n8n:
 *   formId / entryClient / entryDeliverable ← node الـ Config في 03_outbox
 *   رابط الشيت + الـ gid               ← node "صف جديد في الشيت" في 04_feedback
 * عناوين الأسئلة لازم تفضل زي ما هي — node الـ Map في 04_feedback بيقرا الأعمدة بيها.
 */
function createBondFeedbackForm() {
  const form = FormApp.create('Bond · رأيك في الريل')
    .setDescription('30 ثانية بس — رأيك بيفرق معانا 🙏')
    .setCollectEmail(false)
    .setAllowResponseEdits(false)
    .setProgressBar(false)
    .setConfirmationMessage('شكرًا! وصلنا رأيك ✅');
  try { form.setRequireLogin(false); } catch (e) { /* متاحة لحسابات Workspace بس */ }

  // بيتملوا من اللينك — العميل مش محتاج يلمسهم
  const client = form.addTextItem().setTitle('كود العميل').setHelpText('متتغيرش');
  const deliverable = form.addTextItem().setTitle('رقم الريل').setHelpText('متتغيرش').setRequired(true);

  const scale = (title) => form.addScaleItem().setTitle(title).setBounds(1, 5).setLabels('وحش', 'ممتاز').setRequired(true);
  scale('قيّم التصوير');
  scale('قيّم المونتاج');
  scale('قيّم اللوكيشن');

  const wants = form.addMultipleChoiceItem().setTitle('في حاجة عايز نعدلها؟').setRequired(true);

  // صفحة 2 — بتظهر بس لو قال أيوه
  const page2 = form.addPageBreakItem().setTitle('قولنا أكتر');
  form.addCheckboxItem().setTitle('إيه اللي مضايقك')
    .setChoiceValues(['الإضاءة', 'الصوت', 'زاوية التصوير', 'المونتاج والإيقاع', 'الكلام المكتوب', 'المزيكا', 'اللوكيشن', 'حاجة تانية']);
  form.addParagraphTextItem().setTitle('ملاحظتك');
  form.addMultipleChoiceItem().setTitle('عايز نعدّل الريل؟').setChoiceValues(['أيوه', 'لأ']).setRequired(true);

  wants.setChoices([
    wants.createChoice('لأ', FormApp.PageNavigationType.SUBMIT),
    wants.createChoice('أيوه', page2),
  ]);

  // شيت الردود
  const ss = SpreadsheetApp.create('Bond · ردود التقييم');
  form.setDestination(FormApp.DestinationType.SPREADSHEET, ss.getId());
  SpreadsheetApp.flush();
  const responses = ss.getSheets().find((s) => s.getFormUrl());
  const blank = ss.getSheets().find((s) => !s.getFormUrl());
  if (responses && blank) ss.deleteSheet(blank);

  // أرقام الـ entry من لينك متملي
  const pre = form.createResponse()
    .withItemResponse(client.createResponse('CLIENT'))
    .withItemResponse(deliverable.createResponse('DELIVERABLE'))
    .toPrefilledUrl();
  const entry = (v) => (pre.match(new RegExp('(entry\\.\\d+)=' + v)) || [])[1];
  const formId = (form.getPublishedUrl().match(/\/d\/e\/([^/]+)\//) || [])[1];

  const out = [
    '==== انسخ ده وابعته ====',
    "formId: '" + formId + "'",
    "entryClient: '" + entry('CLIENT') + "'",
    "entryDeliverable: '" + entry('DELIVERABLE') + "'",
    'sheetUrl: ' + ss.getUrl(),
    'sheetGid: ' + (responses ? responses.getSheetId() : '?'),
    '==== للتجربة ====',
    'الفورم: ' + form.getPublishedUrl(),
    'التعديل: ' + form.getEditUrl(),
  ].join('\n');
  Logger.log(out);
  return out;
}
