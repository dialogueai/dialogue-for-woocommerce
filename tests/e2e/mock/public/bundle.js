/* E2E stand-in for cdn.nowdialogue.com/prod/build/bundle.js: records the start call, sets the visitor
   cookies the real DialogueAI sets (when DAI_COOKIE), and runs the REAL cart listener + page readers. */
window.DialogueAI = {
  getAllMerchantScripts: function (uid, sid) {
    window.__dialogueStarted = { uid: uid, store_id: sid, dai_cookie: !!window.DAI_COOKIE };
    if (window.DAI_COOKIE) {
      document.cookie = 'dai_v_h=e2eVis_abc123_def456_ghi789; path=/';
      document.cookie = 'dai_v_s=2026100908; path=/';
    }
    var s = document.createElement('script');
    s.src = 'http://localhost:4557/cart-e2e.js';
    s.onload = function () {
      window.__cartEvents = [];
      window.__platform = window.__dlg.detectPlatform(window);
      window.__page = window.__dlg.readPage(window);
      window.__chatPageType = window.__dlg.chatPageType(window);
      window.__dlg.startCartListener({ storeId: sid, onEvent: function (e) { window.__cartEvents.push(e); } });
    };
    document.head.appendChild(s);
  },
};
