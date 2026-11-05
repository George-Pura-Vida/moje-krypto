'use strict';
document.querySelectorAll('[data-password]').forEach(button=>{
  button.addEventListener('click',()=>{
    const input=document.getElementById(button.dataset.password);
    if(!input)return;
    const shown=input.type==='password'; input.type=shown?'text':'password';
    button.setAttribute('aria-pressed',String(shown));
    const label=(input.closest('label').querySelector('.guide-label-row > span')||input.closest('label').querySelector(':scope > span')).textContent.replace('*','').trim();
    button.setAttribute('aria-label',(shown?'Skrýt heslo: ':'Zobrazit heslo: ')+label);
  });
});
const fragment=new URLSearchParams(location.hash.slice(1));
const token=fragment.get('token');
if(token&&/^[a-f0-9]{64}$/.test(token)){
  document.querySelectorAll('input[name="token"]').forEach(input=>{input.value=token;});
  history.replaceState(null,'',location.pathname+location.search);
}
document.querySelectorAll('[data-token-form]').forEach(form=>{
  if(!form.elements.token.value){
    const note=document.createElement('p');note.className='message error';note.textContent='Otevřete úplný odkaz z e-mailu. Pokud vypršel, vyžádejte si nový.';
    form.before(note);form.querySelector('button[type="submit"]').disabled=true;
  }
});


// Only the email-verification form submits automatically. Reset and email changes remain explicit.
if(token&&/^[a-f0-9]{64}$/.test(token)){
  const verification=document.querySelector('[data-auto-verify]');
  if(verification)verification.requestSubmit();
}
