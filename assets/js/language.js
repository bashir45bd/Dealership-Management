/* Lightweight bilingual UI layer. Server data (names, invoices, amounts) remains unchanged. */
(() => {
  const dictionary = {
    bn: {
      'Dashboard':'ড্যাশবোর্ড', 'Business Management':'ব্যবসা ব্যবস্থাপনা', 'Inventory & Stock':'ইনভেন্টরি ও স্টক',
      'Financial Management':'আর্থিক ব্যবস্থাপনা', 'Reports & Analytics':'রিপোর্ট ও বিশ্লেষণ', 'System & Administration':'সিস্টেম ও প্রশাসন',
      'Customers / Parties':'গ্রাহক / পার্টি', 'Sales':'বিক্রয়', 'Collections':'আদায়', 'Advances':'অগ্রিম',
      'Companies':'কোম্পানি', 'Products':'পণ্য', 'Purchases / Receive':'ক্রয় / গ্রহণ', 'Stock Ledger':'স্টক লেজার',
      'Expenses':'খরচ', 'Other Income':'অন্যান্য আয়', 'Company Payment':'কোম্পানি পেমেন্ট', 'Target & Commission':'টার্গেট ও কমিশন',
      'Commission Builder':'কমিশন বিল্ডার', 'User Management':'ব্যবহারকারী ব্যবস্থাপনা', 'Business Settings':'ব্যবসার সেটিংস',
      'My Subscription':'আমার সাবস্ক্রিপশন', 'Audit Logs':'অডিট লগ', 'Database Backup':'ডেটাবেস ব্যাকআপ',
      'My Profile':'আমার প্রোফাইল', 'System Settings':'ব্যবসার সেটিংস', 'Logout':'লগআউট',
      'Features':'ফিচার', 'Pricing':'মূল্য', 'Login':'লগইন', 'Start Free Trial':'ফ্রি ট্রায়াল শুরু করুন',
      'Operate smarter.':'স্মার্টভাবে পরিচালনা করুন।', 'See pricing':'মূল্য দেখুন', 'Get started →':'শুরু করুন →',
      'Compare plans':'প্ল্যান তুলনা করুন', 'Customer login':'কাস্টমার লগইন', 'Platform admin':'প্ল্যাটফর্ম অ্যাডমিন',
      'Monthly':'মাসিক', 'Yearly':'বার্ষিক', 'Payment method':'পেমেন্ট পদ্ধতি', 'Transaction ID / reference':'ট্রানজেকশন আইডি / রেফারেন্স',
      'Create your business account':'আপনার ব্যবসার অ্যাকাউন্ট তৈরি করুন', 'Already have an account?':'ইতোমধ্যে অ্যাকাউন্ট আছে?',
      'MULTI-COMPANY DEALERSHIP PLATFORM':'মাল্টি-কোম্পানি ডিলারশিপ প্ল্যাটফর্ম', 'Scale every':'প্রতিটি', 'dealership.':'ডিলারশিপকে এগিয়ে নিন।',
      'A single secure workspace for companies, products, customers, sales, inventory, ledgers, targets and commission—made for Bangladeshi distribution businesses.':'কোম্পানি, পণ্য, গ্রাহক, বিক্রয়, ইনভেন্টরি, লেজার, টার্গেট ও কমিশনের জন্য এক নিরাপদ ব্যবসায়িক workspace।',
      'Start 14-day trial →':'১৪ দিনের ট্রায়াল শুরু করুন →', 'Business overview':'ব্যবসার সারসংক্ষেপ', 'Your dealership, in control':'আপনার ডিলারশিপ, আপনার নিয়ন্ত্রণে',
      'Everything your dealership needs to grow.':'আপনার ডিলারশিপের বৃদ্ধির জন্য সবকিছু।', 'Sales & inventory':'বিক্রয় ও ইনভেন্টরি', 'Accounting & ledgers':'হিসাব ও লেজার', 'Flexible commission':'নমনীয় কমিশন',
      'Choose the plan that fits today.':'আজকের জন্য উপযুক্ত প্ল্যান বেছে নিন।', 'Companies':'কোম্পানি', 'Products':'পণ্য', 'Users':'ব্যবহারকারী',
      'Business Identity & Contact':'ব্যবসার পরিচয় ও যোগাযোগ', 'Business Logo':'ব্যবসার লোগো', 'Business Name':'ব্যবসার নাম', 'Dealership Address':'ব্যবসার ঠিকানা',
      'Contact Mobile Numbers':'যোগাযোগের মোবাইল নম্বর', 'Official Email':'অফিসিয়াল ইমেইল', 'Inventory & Stock Constraints':'ইনভেন্টরি ও স্টক নিয়ম',
      'Global Low Stock Warning Limit (Bags)':'লো স্টক সতর্কতার সীমা', 'Save All Settings':'সব সেটিংস সংরক্ষণ করুন', 'Update Account Password':'অ্যাকাউন্ট পাসওয়ার্ড পরিবর্তন করুন'
    }
  };
  function applyLanguage(lang) {
    const root=document.documentElement; root.lang=lang;
    const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
    const nodes=[]; while(walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(node=>{
      const parent=node.parentElement; if(!parent || ['SCRIPT','STYLE'].includes(parent.tagName)) return;
      if(!node.__sourceText) node.__sourceText=node.nodeValue;
      const source=node.__sourceText.trim(); if(!source) return;
      const translated=lang==='bn' ? (dictionary.bn[source] || source) : source;
      if(translated!==source || lang==='en') node.nodeValue=node.__sourceText.replace(source,translated);
    });
    document.querySelectorAll('[data-language-toggle]').forEach(button=>button.textContent=lang==='bn'?'English':'বাংলা');
    localStorage.setItem('dealership_language',lang);
  }
  document.addEventListener('DOMContentLoaded',()=>{
    if(!document.querySelector('[data-language-toggle]')){
      const button=document.createElement('button');
      button.type='button'; button.dataset.languageToggle=''; button.className='btn btn-sm btn-outline-light position-fixed bottom-0 end-0 m-3';
      button.style.zIndex='9999'; document.body.appendChild(button);
    }
    applyLanguage(localStorage.getItem('dealership_language')||'en');
    document.querySelectorAll('[data-language-toggle]').forEach(button=>button.addEventListener('click',()=>applyLanguage((localStorage.getItem('dealership_language')||'en')==='en'?'bn':'en')));
    if(location.pathname.endsWith('/admin/manage.php')){
      const heading=document.querySelector('h1');
      if(heading && !document.querySelector('[data-plan-editor-link]')){
        const link=document.createElement('a'); link.dataset.planEditorLink=''; link.href=new URL('plans.php',location.href).href; link.className='btn btn-outline-light btn-sm ms-3'; link.textContent='Edit or delete plans'; heading.insertAdjacentElement('afterend',link);
      }
    }
  });
})();
