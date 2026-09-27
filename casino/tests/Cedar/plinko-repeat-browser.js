// Run with Playwright CLI --filename against the isolated arcade fixture.
async page => {
  if(!page.url().startsWith('http://127.0.0.1:8766/'))throw Error('Fixture only');
  const errors=[],rounds=[];let bets=0;
  const onError=e=>errors.push(e.message);
  const onRequest=r=>{if(r.url().endsWith('/fixture-command')&&r.postDataJSON()?.payload?.action==='drop')bets++;};
  page.on('pageerror',onError);page.on('request',onRequest);
  try {
    // Reproduce the browser's early first timestamp on every animation.
    await page.evaluate(()=>{
      const raf=window.requestAnimationFrame.bind(window);let last=0;
      window.requestAnimationFrame=fn=>{const started=performance.now(),first=started-last>150;last=started;return raf(t=>fn(first?started-1:t));};
    });
    for(let i=0;i<8;i++) {
      await page.getByRole('button',{name:i===0?'Drop ball · 10.00':'New round · 10.00',exact:true}).click();
      await page.waitForFunction(()=>document.getElementById('stage-label').textContent.startsWith('Round complete'),{},{timeout:10000});
      if(await page.getByRole('combobox',{name:'Rows',exact:true}).isDisabled())throw Error('Controls still locked');
      rounds.push(await page.locator('#round-ref').textContent());
    }
    if(new Set(rounds).size!==8||bets!==8||errors.length)throw Error('Repeated-round regression: '+JSON.stringify({bets,errors,rounds}));
    await page.evaluate(()=>{
      const original=CanvasRenderingContext2D.prototype.arc;
      CanvasRenderingContext2D.prototype.arc=function(...args){CanvasRenderingContext2D.prototype.arc=original;throw Error('Injected canvas failure');};
    });
    await page.getByRole('button',{name:'New round · 10.00',exact:true}).click();
    await page.getByRole('button',{name:'Recover existing round',exact:true}).waitFor();
    const failedRound=await page.locator('#round-ref').textContent();
    await page.getByRole('button',{name:'Recover existing round',exact:true}).click();
    await page.waitForFunction(()=>document.getElementById('stage-label').textContent.startsWith('Round complete'));
    if(bets!==9||failedRound!==await page.locator('#round-ref').textContent())throw Error('Recovery placed a wager or changed round');
    return {completedConsecutiveRounds:8,forcedEarlyTimestamps:true,renderFailureRecovered:true,noRecoveryWager:true,uncaughtErrors:errors};
  } finally {page.off('pageerror',onError);page.off('request',onRequest);}
}
