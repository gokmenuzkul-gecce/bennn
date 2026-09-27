// Isolated presentation regression; never intercept a real wallet response.
async page => {
  if(!page.url().startsWith('http://127.0.0.1:8766/'))throw Error('Fixture only');
  const errors=[];let wagers=0,won=false;
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/fixture-command',async route=>{
    const response=await route.fetch(),r=await response.json();
    if(route.request().postDataJSON().payload.action==='play'){
      wagers++;r.is_win=won;r.win_amount=won?'20.00':'0.00';
    }
    await route.fulfill({response,json:r});
  });
  for(const success of [false,true]){
    won=success;
    await page.getByRole('button',{name:success?'New round · 10.00':'Launch · 10.00',exact:true}).click();
    await page.locator('.limbo-flight.climbing').waitFor();
    if(!await page.getByRole('spinbutton',{name:'Target multiplier'}).isDisabled())throw Error('Target not locked');
    await page.locator(success?'.limbo-flight.arrived':'.limbo-flight.crashed').waitFor();
    await page.waitForFunction(()=>document.getElementById('stage-label').textContent.startsWith('Round complete'));
    const expected=success?'TARGET REACHED':'CRASHED';
    if(await page.locator('#limbo-status').textContent()!==expected)throw Error('Wrong outcome');
    await page.screenshot({path:`output/playwright/limbo-${success?'success':'crash'}.png`});
    await page.waitForTimeout(500);
    if(await page.locator('#limbo-status').textContent()!==expected)throw Error('Result cleared');
  }
  await page.setViewportSize({width:375,height:667});
  await page.screenshot({path:'output/playwright/limbo-mobile.png'});
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Mobile overflow');
  if(wagers!==2||errors.length)throw Error(JSON.stringify({wagers,errors}));
  return {wagers,errors,crashAndSuccess:true,retained:true,mobile:true};
}
