// Playwright CLI run-code function. Use only the loopback arcade fixture router.
async page => {
  if (!page.url().startsWith('http://127.0.0.1:8766/')) throw Error('Fixture required');
  const bets=[];
  const observe=request=>{
    if(request.url().endsWith('/fixture-command')) {
      const body=request.postDataJSON();
      if(body.payload.action==='play') bets.push(body.payload.picks);
    }
  };
  page.on('request',observe);
  const check=(ok,message)=>{if(!ok)throw Error(message);};
  try {
    check(await page.getByRole('button',{name:'Choose 5 more numbers',exact:true}).isDisabled(),'Fresh ticket must be empty');
    for(const n of [1,2,3,4,5]) await page.getByRole('button',{name:String(n),exact:true}).click();
    check(bets.length===0,'Selecting numbers must not wager');
    await page.getByRole('button',{name:'Draw selected numbers · 10.00',exact:true}).click();
    check(await page.getByRole('button',{name:'1',exact:true}).isDisabled(),'Ticket must lock during draw');
    await page.waitForFunction(()=>document.getElementById('stage-label').textContent.startsWith('Round complete'));
    const receipt=await page.locator('#round-ref').textContent();
    const result=await page.locator('.keno-last-result').textContent();
    await page.getByRole('button',{name:'1',exact:true}).click();
    check(await page.getByRole('button',{name:'Choose 1 more number',exact:true}).isDisabled(),'Incomplete edited ticket must not wager');
    await page.getByRole('button',{name:'6',exact:true}).click();
    check(bets.length===1,'Editing completed board must not wager');
    check(await page.locator('.keno-last-result').textContent()===result,'Previous ticket/draw must remain intact');
    check(await page.locator('#round-ref').textContent()===receipt,'Previous receipt must remain intact');
    await page.getByRole('button',{name:'Draw selected numbers · 10.00',exact:true}).click();
    await page.waitForFunction(()=>document.getElementById('stage-label').textContent.startsWith('Round complete'));
    check(JSON.stringify(bets)===JSON.stringify([[1,2,3,4,5],[2,3,4,5,6]]),'Exactly one wager per click with edited numbers');
    await page.getByRole('button',{name:'Clear picks',exact:true}).click();
    check(await page.getByRole('button',{name:'Choose 5 more numbers',exact:true}).isDisabled(),'Clear picks disables Draw');
    check(bets.length===2,'Clear must not wager');
    await page.screenshot({path:'output/playwright/keno-selection-fixed-mobile.png'});
    return {passed:true,bets,previousResultPreserved:true};
  } finally {page.off('request',observe);}
}
