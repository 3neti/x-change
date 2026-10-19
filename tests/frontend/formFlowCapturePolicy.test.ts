import {mount,flushPromises} from '@vue/test-utils';
import {h} from 'vue';
import {afterEach,beforeEach,describe,expect,it,vi} from 'vitest';
import Presentation from '@/pages/form-flow/core/components/FormFlowPresentation.vue';
import Signature from '../../vendor/3neti/form-handler-signature/stubs/resources/js/pages/form-flow/signature/components/SignatureCapture.vue';
import Selfie from '../../vendor/3neti/form-handler-selfie/stubs/resources/js/pages/form-flow/selfie/components/SelfieCapture.vue';

vi.mock('../../vendor/3neti/form-handler-selfie/stubs/resources/js/pages/form-flow/selfie/components/CameraPermissionAlert.vue',()=>({default:{template:'<div />',methods:{show(){}}}}));
let resize:()=>void;
const context={scale:vi.fn(),beginPath:vi.fn(),moveTo:vi.fn(),lineTo:vi.fn(),stroke:vi.fn(),clearRect:vi.fn(),drawImage:vi.fn()};
const getUserMedia=vi.fn();
const stop=vi.fn();
const profile={schema:'form-flow.presentation.v1',brand:{name:null,logo:null,placement:'top_left',logo_height:32,logo_max_width:112},layout:{density:'compact',capture_surface:'contained',scrolling:'when_needed'},actions:{placement:'inline',primary_label:null,secondary_label:null,reserve_content_space:true},progress:{show:false},diagnostics:{show_versions:false}} as any;
beforeEach(()=>{
 vi.clearAllMocks();
 vi.stubGlobal('requestAnimationFrame',(callback:()=>void)=>{callback();return 1});vi.stubGlobal('cancelAnimationFrame',vi.fn());
 vi.stubGlobal('ResizeObserver',class {constructor(callback:()=>void){resize=callback}observe(){}disconnect(){}});
 vi.spyOn(HTMLCanvasElement.prototype,'getContext').mockReturnValue(context as any);
 vi.spyOn(HTMLCanvasElement.prototype,'toDataURL').mockReturnValue('data:image/png;base64,c3ludGhldGlj');
 getUserMedia.mockResolvedValue({getTracks:()=>[{stop}]});
 Object.defineProperty(navigator,'mediaDevices',{configurable:true,value:{getUserMedia}});
});
afterEach(()=>{vi.restoreAllMocks();vi.unstubAllGlobals();});
function render(component:any,props:any){return mount(Presentation,{props:{presentation:profile},slots:{default:()=>h(component,props)}})}
describe('capture policy preserves interaction boundaries',()=>{
 it('keeps camera capture inert in previews, including user activation',async()=>{
  const wrapper=render(Selfie,{previewMode:true});await flushPromises();
  await wrapper.find('button[type=button]').trigger('click');
  expect(getUserMedia).not.toHaveBeenCalled();
  expect(context.drawImage).not.toHaveBeenCalled();wrapper.unmount();
 });
 it('keeps signature previews inert and preserves existing signatures on surface resize',async()=>{
  let width=300;
  vi.spyOn(HTMLCanvasElement.prototype,'getBoundingClientRect').mockImplementation(()=>({width,height:200,left:0,top:0} as DOMRect));
  const preview=render(Signature,{previewMode:true});await flushPromises();
  await preview.find('canvas').trigger('mousedown',{clientX:10,clientY:10});await preview.find('canvas').trigger('mousemove',{clientX:20,clientY:20});
  expect(context.lineTo).not.toHaveBeenCalled();preview.unmount();
  const live=render(Signature,{previewMode:false});await flushPromises();
  await live.find('canvas').trigger('mousedown',{clientX:10,clientY:10});await live.find('canvas').trigger('mousemove',{clientX:20,clientY:20});
  expect(context.lineTo).toHaveBeenCalled();width=400;resize();
  expect(context.drawImage).toHaveBeenCalled();
  expect(live.find('canvas').element.width).toBe(400*window.devicePixelRatio);live.unmount();
 });
 it('captures and retakes only synthetic camera frames in live capture mode',async()=>{
  const wrapper=render(Selfie,{previewMode:false});await flushPromises();
  const video=wrapper.find('video').element;
  Object.defineProperties(video,{videoWidth:{value:640},videoHeight:{value:480}});
  await wrapper.findAll('button').find(b=>b.text()==='Capture Photo')!.trigger('click');
  expect(context.drawImage).toHaveBeenCalled();expect(stop).toHaveBeenCalled();
  await wrapper.findAll('button').find(b=>b.text()==='Retake')!.trigger('click');await flushPromises();
  expect(getUserMedia).toHaveBeenCalledTimes(2);wrapper.unmount();
 });
});
