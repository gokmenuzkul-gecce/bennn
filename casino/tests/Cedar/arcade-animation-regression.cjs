const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('CedarGames/_arcade/arcade.js', 'utf8');
const body = source.slice(source.indexOf('  function animate('), source.indexOf('  async function reveal('));
function harness() {
  const queue = [], document = {hidden:false};
  const context = {document, performance:{now:()=>100}, requestAnimationFrame:fn=>queue.push(fn)};
  vm.createContext(context); vm.runInContext(body+'\nthis.animate=animate;',context);
  return {animate:context.animate, document, tick:time=>{assert.ok(queue.length,'scheduled frame');queue.shift()(time);}, queue};
}
(async()=>{
  const h=harness(), progress=[];
  const done=h.animate(200,t=>{assert.ok(t>=0&&t<=1,'progress must stay in [0,1]');progress.push(t);});
  // rAF uses the frame start timestamp, which can precede performance.now().
  h.tick(99);h.tick(150);h.tick(250);h.tick(350);await done;
  assert.equal(progress[0],0);assert.equal(progress.at(-1),1);
  const failure=harness();const rejected=failure.animate(200,()=>{throw new Error('Canvas failure');});
  const rejection=assert.rejects(rejected,/Canvas failure/);
  failure.tick(116);await rejection;assert.equal(failure.queue.length,0);
  const paused=harness();const values=[];const resumed=paused.animate(100,t=>values.push(t));
  paused.document.hidden=true;paused.tick(200);assert.equal(values.at(-1),0);
  paused.document.hidden=false;paused.tick(250);paused.tick(300);await resumed;assert.equal(values.at(-1),1);
  console.log('PASS: early frame timestamp, render-error rejection, hidden/resumed animation');
})().catch(error=>{console.error(error);process.exitCode=1;});
