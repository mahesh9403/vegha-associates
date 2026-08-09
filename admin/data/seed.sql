-- Schema + the three seeded articles for the Insights admin.
--
-- admin/data/blog.sqlite is deliberately NOT in git: this site auto-deploys from
-- the repo, so a tracked database would overwrite the live one on every push,
-- destroying the admin password and every article published since. lib.php runs
-- this file automatically when it finds no database, so a fresh host self-seeds
-- and an existing host is left untouched.

CREATE TABLE IF NOT EXISTS attempts(ip TEXT, ts INTEGER);
CREATE TABLE IF NOT EXISTS posts(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      slug TEXT UNIQUE NOT NULL, title TEXT NOT NULL, h1 TEXT, short TEXT,
      excerpt TEXT, meta_title TEXT, meta_desc TEXT, category TEXT, tags TEXT,
      author TEXT, featured_img TEXT, body_html TEXT, read_mins INTEGER,
      status TEXT DEFAULT 'draft', published_at TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO "posts" VALUES(1,'statutory-audit-vs-tax-audit','Statutory Audit or Tax Audit? What Applies to Your Business','Statutory audit or tax audit? What actually applies to your business','Audit applicability','A plain-English guide to audit applicability in India: statutory audits under the Companies Act, LLP thresholds, and the Section 44AB tax audit limits including the Rs. 10 crore digital-transactions relaxation.','Statutory Audit or Tax Audit? What Applies to Your Business | VEGHA & ASSOCIATES','A plain-English guide to audit applicability in India: statutory audits under the Companies Act, LLP thresholds, and the Section 44AB tax audit limits including the Rs. 10 crore digital-transactions relaxation.','Assurance','Assurance','The Partners, VEGHA & ASSOCIATES','assets/img/service-assurance-audit-900.webp','<p>"Do we need an audit?" is one of the most common questions we hear from growing businesses, and one of the most commonly misunderstood, because India has two entirely different audit regimes that get mixed up in conversation. One comes from company law, the other from income-tax law, and a business can need both, either, or neither.</p>

<h2>The statutory audit: a creature of company law</h2>
<p>If your business is a <strong>company</strong> registered under the Companies Act, 2013 (private limited or public), the answer is simple: a statutory audit is mandatory <em>every year, regardless of turnover</em>. A company with zero revenue still appoints an auditor and files audited financial statements. There is no threshold, no exemption for small companies, and no waiting period for new incorporations: the first audit covers your first financial year.</p>
<p>An <strong>LLP</strong> gets more breathing room. Audit becomes mandatory only when either of two limits is crossed:</p>
<ul>
<li>turnover exceeds <strong>Rs. 40 lakh</strong> in the financial year, or</li>
<li>partners'' contribution exceeds <strong>Rs. 25 lakh</strong>.</li>
</ul>
<p>Proprietorships and ordinary partnership firms have no statutory audit requirement at all under this regime, which is precisely where the second regime takes over.</p>

<h2>The tax audit: Section 44AB of the Income-tax Act</h2>
<p>The tax audit applies to <em>any</em> business form (proprietorship, partnership, LLP or company) once turnover crosses the prescribed limits. The current structure:</p>
<table>
<thead><tr><th>Taxpayer</th><th>Trigger</th><th>Threshold</th></tr></thead>
<tbody>
<tr><td>Business (general)</td><td>Turnover exceeds</td><td>Rs. 1 crore</td></tr>
<tr><td>Business (digital)</td><td>Turnover exceeds, where cash receipts <em>and</em> cash payments are each 5% or less of totals</td><td>Rs. 10 crore</td></tr>
<tr><td>Profession</td><td>Gross receipts exceed</td><td>Rs. 50 lakh</td></tr>
</tbody>
</table>
<p>The Rs. 10 crore relaxation is a substantial concession for businesses that transact digitally, but note the test is applied to <strong>both</strong> receipts and payments. A business that collects everything through the banking system but pays wages in cash can fail the payments leg and fall back to the Rs. 1 crore limit.</p>

<h2>The presumptive-taxation wrinkle</h2>
<p>Small businesses opting for presumptive taxation under <strong>Section 44AD</strong> (or professionals under <strong>44ADA</strong>) generally stay outside the tax audit net. The trap: if you have opted for presumptive taxation and then declare profits <em>below</em> the presumptive rate while your income exceeds the basic exemption, a tax audit is triggered even at low turnover. Businesses that dip in and out of the presumptive scheme also face a five-year lock-out rule that catches many by surprise.</p>

<blockquote>A company always needs a statutory audit. Everyone, company or not, needs a tax audit once the 44AB numbers are crossed. The two tests are independent; run both.</blockquote>

<h2>What a tax audit actually involves</h2>
<p>The auditor examines the books and reports in <strong>Form 3CA/3CB and Form 3CD</strong>, a detailed statement covering everything from related-party payments and loans accepted in cash to TDS compliance and inventory valuation. The 3CD is effectively a disclosure X-ray of your business that the Income-tax Department reads carefully, which is why the quality of preparation matters as much as the filing itself.</p>

<h2>A quick self-check</h2>
<ol>
<li><strong>Are you a company?</strong> Statutory audit: yes, always. Then run the 44AB test separately.</li>
<li><strong>Are you an LLP?</strong> Check the Rs. 40 lakh / Rs. 25 lakh limbs, then run the 44AB test.</li>
<li><strong>Proprietor or firm?</strong> Only the 44AB test applies. Check turnover against Rs. 1 crore / 10 crore (business) or Rs. 50 lakh (profession).</li>
<li><strong>On presumptive taxation?</strong> Confirm you are declaring at or above the presumptive rate, and that the lock-out rule is not in play.</li>
</ol>
<p>Deadlines matter: the tax audit report has a statutory due date ahead of the return-filing date, and late filing attracts a penalty of 0.5% of turnover (capped at Rs. 1.5 lakh). If you are anywhere near a threshold, the time to confirm applicability is early in the year, not in September.</p>',6,'published','2026-07-14','2026-07-14','2026-07-14');
INSERT INTO "posts" VALUES(2,'gst-notice-response-playbook','A GST Notice Is Not a Crisis: A Practical Response Playbook','A GST notice is not a crisis: a practical <em class="accent">response playbook</em>','GST notices','How to respond to GST scrutiny notices in India: ASMT-10, DRC-01A intimations and show-cause notices, the deadlines that matter, and the mistakes that turn a routine query into a tax demand.','A GST Notice Is Not a Crisis: A Practical Response Playbook | VEGHA & ASSOCIATES','How to respond to GST scrutiny notices in India: ASMT-10, DRC-01A intimations and show-cause notices, the deadlines that matter, and the mistakes that turn a routine query into a tax demand.','GST','Goods & Services Tax','The Partners, VEGHA & ASSOCIATES','assets/img/indirect-taxation-sheet-900.webp','<p>Most GST notices are not accusations. They are the system doing what it was designed to do: comparing the returns you filed against each other (GSTR-1 against GSTR-3B, GSTR-3B against GSTR-2B, e-way bills against declared turnover) and asking about the gaps. Businesses that treat a notice as a routine reconciliation exercise usually close it in one round. Businesses that panic, ignore it, or reply casually are the ones that end up with demands.</p>

<h2>Know what you have received</h2>
<p>The form number at the top of the notice tells you what stage you are at, and how much room you have:</p>
<ul>
<li><strong>ASMT-10</strong>: a scrutiny notice pointing out discrepancies in your returns. This is the earliest, most benign stage. You reply in ASMT-11, either accepting the discrepancy (and paying) or explaining it.</li>
<li><strong>DRC-01A</strong>: an <em>intimation</em> of tax ascertained as payable, before a formal show-cause notice. This is a genuine opportunity: pay or explain now and proceedings may never begin.</li>
<li><strong>DRC-01 / show-cause notice</strong>: formal adjudication has started under Section 73 (normal cases) or Section 74 (fraud or wilful misstatement, with steeper penalties). Timelines and stakes both escalate.</li>
<li><strong>Registration, refund and e-way bill notices</strong>: narrower issues (REG, RFD series) with their own short reply windows.</li>
</ul>

<h2>The discipline that closes notices</h2>
<ol>
<li><strong>Diary the deadline the day the notice arrives.</strong> Most scrutiny replies are due within 30 days; some windows are 7 or 15 days. Extensions exist but must be sought before expiry, not after.</li>
<li><strong>Reconcile before you write.</strong> Nearly every ASMT-10 traces to one of a handful of mismatches: input credit claimed in 3B versus what suppliers reported into your 2B; turnover in GSTR-1 versus 3B; e-way bill values versus returns. Rebuild the reconciliation month by month; the explanation usually falls out of the working.</li>
<li><strong>Answer the question asked.</strong> A reply that addresses each pointed discrepancy, line by line, with annexures, closes files. A general letter about your compliance record does not.</li>
<li><strong>Attach evidence, not assertions.</strong> Supplier invoices, payment proofs, ledger extracts, e-way bill printouts. The officer needs material to record a finding in your favour.</li>
<li><strong>Reply on the portal, keep the acknowledgement.</strong> A reply handed over in person but never uploaded does not exist for adjudication purposes.</li>
</ol>

<h2>Three mistakes that convert queries into demands</h2>
<p><strong>Ignoring the notice.</strong> Non-reply leads to best-judgement assessment: the officer decides the numbers without you, and recovering from that position costs multiples of what a timely reply would have.</p>
<p><strong>Paying without analysis.</strong> Some intimations rest on mechanical comparisons that ignore legitimate timing differences such as credit notes, imports and reverse-charge entries. Paying "to make it go away" concedes a position you may have defended easily, and invites the same query every year after.</p>
<p><strong>Casual admissions.</strong> A sentence written loosely in a reply can be quoted back at adjudication and appeal for years. Anything you concede should be conceded deliberately.</p>

<blockquote>The best GST litigation strategy is a reconciliation habit: match 2B to 3B monthly, and the annual notice season becomes paperwork instead of firefighting.</blockquote>

<h2>When to bring in representation</h2>
<p>A first ASMT-10 with a small, explainable gap is manageable in-house. Bring in professional representation when the amounts are material, when the notice cites Section 74, when the same issue spans multiple years, or when a reply has already gone wrong. Departmental representation is a core part of our indirect tax practice, from drafting ASMT-11 replies through appearance before adjudicating and appellate authorities.</p>',7,'published','2026-07-02','2026-07-02','2026-07-02');
INSERT INTO "posts" VALUES(3,'virtual-cfo-for-msme','The Virtual CFO: When an MSME Should Hire Judgement, Not Headcount','The Virtual CFO: hire <em class="accent">judgement</em>, not headcount','Virtual CFO','What a Virtual CFO actually does for a growing Indian MSME: MIS discipline, cash-flow forecasting, lender readiness, pricing decisions, and the five signs your business has outgrown its bookkeeper.','The Virtual CFO: When an MSME Should Hire Judgement, Not Headcount | VEGHA & ASSOCIATES','What a Virtual CFO actually does for a growing Indian MSME: MIS discipline, cash-flow forecasting, lender readiness, pricing decisions, and the five signs your business has outgrown its bookkeeper.','Business Advisory','Business Advisory','The Partners, VEGHA & ASSOCIATES','assets/img/accounting-support-sheet-900.webp','<p>Somewhere between Rs. 2 crore and Rs. 50 crore of revenue, most businesses hit the same wall: the accounts are maintained, the returns are filed, and yet the owner is flying blind. The bookkeeper can tell you what happened; nobody in the building can tell you what it <em>means</em>, or what to do next month because of it. That gap is not a headcount problem. A full-time CFO at that stage costs Rs. 30 to 80 lakh a year and would be underutilised. The gap is a judgement problem, and it is exactly what fractional ("virtual") CFO services exist to fill.</p>

<h2>Five signs you have outgrown the bookkeeper</h2>
<ol>
<li><strong>You discover your profit once a year,</strong> when the accountant finalises the statements, and it is routinely different from what you believed all year.</li>
<li><strong>Cash surprises you.</strong> The order book is healthy but salaries are a scramble; you learn about a GST or TDS outflow the week it is due.</li>
<li><strong>The bank asks questions you cannot answer quickly.</strong> Projections, CMA data, receivables ageing: every enhancement request becomes a two-week fire drill.</li>
<li><strong>Pricing is folklore.</strong> Nobody can say which product, customer or branch actually makes money, so pricing decisions are made on gut feel and history.</li>
<li><strong>Growth decisions wait.</strong> A new machine, a second unit, an export order, all evaluated on optimism because no one models the numbers.</li>
</ol>

<h2>What a Virtual CFO actually does all month</h2>
<p>The label covers a defined rhythm of work, not an occasional phone call:</p>
<ul>
<li><strong>A monthly MIS you can act on</strong>: profitability by segment, working-capital movement, budget-versus-actual with explanations for the variances, not just the numbers.</li>
<li><strong>A rolling 13-week cash-flow forecast</strong>: the single most valuable discipline for an MSME, because it converts cash surprises into scheduled events.</li>
<li><strong>Receivables and payables management</strong>: ageing pressure, credit limits for customers, negotiating supplier terms with data.</li>
<li><strong>Lender and investor readiness</strong>: CMA data, projections and covenant tracking maintained continuously, so a facility enhancement or diligence exercise starts from a prepared position.</li>
<li><strong>Decision support</strong>: capex evaluations, pricing reviews, make-versus-buy calls, all tied to your own numbers rather than industry generalities.</li>
<li><strong>Compliance oversight</strong>: not doing the filings, but making sure the calendar of GST, TDS, PF/ESI and ROC obligations is owned, tracked and never a surprise.</li>
</ul>

<blockquote>The bookkeeper records the past. The Virtual CFO argues with the future, and makes the argument with your own data.</blockquote>

<h2>What it costs, and what it replaces</h2>
<p>A fractional engagement typically runs at a small fraction of a full-time CFO''s cost, scaled to cadence: a fortnightly review and monthly MIS for a smaller business, up to weekly involvement for a company preparing for funding. Because the work is delivered by a firm rather than an individual, you also get continuity: the discipline does not resign, go on leave, or leave with the spreadsheet knowledge in one head.</p>

<h2>Where to start</h2>
<p>The honest starting point is a diagnostic month: reconcile the books to a dependable baseline, build the first MIS and the first 13-week cash forecast, and agree the three numbers the owner will watch weekly. Most clients know within one quarter whether the discipline is paying for itself. In our experience, the first prevented cash crisis usually settles the question.</p>',6,'published','2026-06-18','2026-06-18','2026-06-18');
CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY, value TEXT);
